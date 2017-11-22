<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;

class DashboardController
{
    public function index()
    {
        return new Response(
            '<html><body>This is the index.</body></html>'
        );
    }
}
