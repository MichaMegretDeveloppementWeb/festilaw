<?php

declare(strict_types=1);

return [

    // The kit lays its own tags down in the pages it draws: its stylesheets and
    // its script at the top of the head, the notification container before the
    // end of the body.
    //
    // It ADDS tags, and never edits one you wrote. Your opening tag comes out
    // exactly as you left it, whatever this setting says: the dark mode is
    // declared rather than imposed, and the diagnostic names what is missing.
    //
    // It lays down only what is missing. A layout that says `@falconStyles` and
    // `@falconScripts` keeps its own placement, and a layout that writes
    // everything itself is left untouched.
    //
    // Turn this off to take it all back. The page must then carry
    // `@falconStyles` and `@falconScripts`, and your own stylesheet must open
    // on the suite's nine layers — otherwise the kit's styles win over yours,
    // without a single error to say so.
    'inject' => true,

    'toast' => [
        // An optional three-pixel bar showing the notification's remaining
        // lifetime. Hidden when the reader asks for reduced motion.
        // Festilaw : activee (Micha, 24/11/2026), pour savoir combien de temps la notification reste a l'ecran.
        'progress' => true,
    ],

    'assets' => [

        // The public directory packages publish their compiled files into,
        // relative to public_path(). Change it and republish; the kit builds
        // its URLs from this value, so nothing else has to know.
        'path' => 'vendor/falcon',

        // Check that a published copy still matches the file the package
        // ships before serving it.
        //
        // Leave it on. It is what keeps a stale copy from being served in
        // silence after a deployment that forgot to republish: the check
        // turns that into an error naming the package and the command to run.
        //
        // Turning it off saves two file reads per asset per request. That is
        // a defensible trade on a very busy host deploying atomically, and it
        // is paid for in stale copies nobody notices.
        'verify' => true,

    ],

];
