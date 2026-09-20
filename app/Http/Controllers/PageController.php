<?php

namespace App\Http\Controllers;

use App\Models\LegalDocument;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PageController extends Controller
{
    /** Yasal metinler — daima yürürlükteki sürüm gösterilir. */
    public function legal(string $slug)
    {
        $belge = LegalDocument::current($slug);

        if (! $belge) {
            throw new NotFoundHttpException;
        }

        return view('vitrin.sayfa', compact('belge'));
    }

    public function contact()
    {
        return view('vitrin.iletisim');
    }
}
