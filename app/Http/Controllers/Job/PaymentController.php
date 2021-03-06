<?php

namespace Coyote\Http\Controllers\Job;

use Coyote\Events\PaymentPaid;
use Coyote\Http\Controllers\Controller;
use Coyote\Http\Forms\Job\PaymentForm;
use Coyote\Payment;
use Coyote\Repositories\Contracts\CountryRepositoryInterface as CountryRepository;
use Coyote\Repositories\Contracts\CouponRepositoryInterface as CouponRepository;
use Coyote\Services\Invoice\Calculator;
use Coyote\Services\Invoice\CalculatorFactory;
use Coyote\Services\UrlBuilder\UrlBuilder;
use Coyote\Services\Invoice\Generator as InvoiceGenerator;
use Illuminate\Database\Connection as Db;
use Illuminate\Http\Request;
use Braintree\Configuration;
use Braintree\ClientToken;
use Braintree\Transaction;
use Braintree\Exception;

class PaymentController extends Controller
{
    /**
     * @var InvoiceGenerator
     */
    private $invoice;

    /**
     * @var CountryRepository
     */
    private $country;

    /**
     * @var Db
     */
    private $db;

    /**
     * @var CouponRepository
     */
    private $coupon;

    /**
     * @var array
     */
    private $vatRates;

    /**
     * @param InvoiceGenerator $invoice
     * @param Db $db
     * @param CountryRepository $country
     * @param CouponRepository $coupon
     */
    public function __construct(InvoiceGenerator $invoice, Db $db, CountryRepository $country, CouponRepository $coupon)
    {
        parent::__construct();

        $this->invoice = $invoice;
        $this->db = $db;
        $this->country = $country;
        $this->coupon = $coupon;

        $this->middleware(function (Request $request, $next) {
            /** @var \Coyote\Payment $payment */
            $payment = $request->route('payment');

            abort_if($payment->status_id == Payment::PAID, 404);

            return $next($request);
        });

        $this->breadcrumb->push('Praca', route('job.home'));
        $this->vatRates = $this->country->vatRatesList();

        Configuration::environment(config('services.braintree.env'));
        Configuration::merchantId(config('services.braintree.merchant_id'));
        Configuration::publicKey(config('services.braintree.public_key'));
        Configuration::privateKey(config('services.braintree.private_key'));
    }

    /**
     * @param \Coyote\Payment $payment
     * @return \Illuminate\View\View
     */
    public function index($payment)
    {
        $this->breadcrumb->push($payment->job->title, UrlBuilder::job($payment->job));
        $this->breadcrumb->push('Promowanie ogłoszenia');

        /** @var PaymentForm $form */
        $form = $this->getForm($payment);

        // calculate price based on payment details
        $calculator = CalculatorFactory::payment($payment);
        $calculator->vatRate = $this->vatRates[$form->get('invoice')->get('country_id')->getValue()] ?? $calculator->vatRate;

        $coupon = $this->coupon->firstOrNew(['code' => $form->get('coupon')->getValue()]);

        $this->request->attributes->set('validate_coupon_url', route('job.coupon'));

        return $this->view('job.payment', [
            'form'              => $form,
            'payment'           => $payment,
            'vat_rates'         => $this->vatRates,
            'calculator'        => $calculator->toArray(),
            'default_vat_rate'  => $payment->plan->vat_rate,
            'client_token'      => ClientToken::generate(),
            'coupon'            => $coupon->toArray()
        ]);
    }

    /**
     * @param \Coyote\Payment $payment
     * @return \Illuminate\Http\RedirectResponse
     * @throws \Exception
     */
    public function process($payment)
    {
        /** @var PaymentForm $form */
        $form = $this->getForm($payment);
        $form->validate();

        $calculator = CalculatorFactory::payment($payment);
        $calculator->vatRate = $this->vatRates[$form->get('invoice')->getValue()['country_id']] ?? $calculator->vatRate;

        $coupon = $this->coupon->findBy('code', $form->get('coupon')->getValue());
        $calculator->setCoupon($coupon);

        return $this->handlePayment(function () use ($payment, $form, $calculator, $coupon) {
            $this->makeTransaction($payment, $calculator);

            // save invoice data. keep in mind that we do not setup invoice number until payment is done.
            /** @var \Coyote\Invoice $invoice */
            $invoice = $this->invoice->create(
                array_merge($form->get('enable_invoice')->isChecked() ? $form->all()['invoice'] : [], ['user_id' => $this->auth->id]),
                $payment,
                $calculator
            );

            if ($coupon) {
                $payment->coupon_id = $coupon->id;

                $coupon->delete();
            }

            // associate invoice with payment
            $payment->invoice()->associate($invoice);
            $payment->save();

            // boost job offer, send invoice and reindex
            event(new PaymentPaid($payment, $this->auth));

            return redirect()
                ->to(UrlBuilder::job($payment->job))
                ->with('success', trans('payment.success'));
        });
    }

    /**
     * @param \Coyote\Payment $payment
     * @return \Coyote\Services\FormBuilder\Form
     */
    private function getForm($payment)
    {
        return $this->createForm(PaymentForm::class, $payment);
    }

    /**
     * @param Payment $payment
     * @param Calculator $calculator
     * @throws Exception\ValidationsFailed
     */
    private function makeTransaction(Payment $payment, Calculator $calculator)
    {
        if (!$calculator->grossPrice()) {
            return;
        }

        /** @var mixed $result */
        $result = Transaction::sale([
            'amount'                => number_format($calculator->grossPrice(), 2, '.', ''),
            'orderId'               => $payment->id,
            'paymentMethodNonce'    => $this->request->input("payment_method_nonce"),
            'options' => [
                'submitForSettlement' => true
            ]
        ]);

        /** @var $result \Braintree\Result\Error */
        if (!$result->success || is_null($result->transaction)) {
            /** @var \Braintree\Error\Validation $error */
            $error = array_first($result->errors->deepAll());
            logger()->error(var_export($result, true));

            if (is_null($error)) {
                throw new Exception\ValidationsFailed();
            }

            throw new Exception\ValidationsFailed($error->message, $error->code);
        }

        logger()->debug('Successfully payment', ['result' => $result]);
    }

    /**
     * @param \Exception $exception
     * @param string $message
     * @return \Illuminate\Http\RedirectResponse
     */
    private function handlePaymentException($exception, $message)
    {
        $back = back()->withInput()->with('error', $message);

        // remove sensitive data
        $this->request->merge(['number' => '***', 'cvc' => '***']);
        $_POST['number'] = $_POST['cvc'] = '***';

        $this->db->rollBack();
        // log error. sensitive data won't be saved (we removed them)
        logger()->error($exception);

        if (app()->environment('production')) {
            app('sentry')->captureException($exception);
        }

        return $back;
    }

    /**
     * @param \Closure $callback
     * @return \Illuminate\Http\RedirectResponse|mixed
     */
    private function handlePayment(\Closure $callback)
    {
        $this->db->beginTransaction();

        try {
            $result = $callback();
            $this->db->commit();

            return $result;
        } catch (Exception\Authentication $e) {
            return $this->handlePaymentException($e, trans('payment.forbidden'));
        } catch (Exception\Authorization $e) {
            return $this->handlePaymentException($e, trans('payment.unauthorized'));
        } catch (Exception\Timeout $e) {
            return $this->handlePaymentException($e, trans('payment.timeout'));
        } catch (Exception\ServerError $e) {
            return $this->handlePaymentException($e, trans('payment.unauthorized'));
        } catch (Exception\ValidationsFailed $e) {
            return $this->handlePaymentException($e, $e->getMessage() ?: trans('payment.validation'));
        } catch (\Exception $e) {
            return $this->handlePaymentException($e, trans('payment.unhandled'));
        }
    }
}
