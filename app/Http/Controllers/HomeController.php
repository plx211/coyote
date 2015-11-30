<?php

namespace Coyote\Http\Controllers;

use Coyote\Repositories\Contracts\MicroblogRepositoryInterface as Microblog;
use Coyote\Repositories\Contracts\SessionRepositoryInterface as Session;
use Coyote\Stream\Stream;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function index(Request $request, Session $session, Microblog $microblog, Stream $stream)
    {
        $viewers = new \Coyote\Session\Viewers($session, $request);

        // tymczasowo naglowki tylko dla mikroblogow, a nie dla forum
        $activities = $stream->take(10, 0, ['Microblog', 'Comment'], ['Create', 'Update']);
        $microblogs = $microblog->take(10);

        return view('home', [
            'viewers'                   => $viewers->render(),
            'microblogs'                => $microblogs->all(),
            'activities'                => $activities
        ]);
    }
}
