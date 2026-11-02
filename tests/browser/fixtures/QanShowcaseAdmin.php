<?php

namespace Restruct\QanBrowser;

use SilverStripe\Admin\ModelAdmin;

/**
 * README-SCREENSHOT FIXTURE ONLY - the "Events" CMS section the README screenshots are taken in
 * (tests/browser/screenshots/readme.shot.ts). Never loaded by a real install: tests/browser/
 * carries a _manifest_exclude marker, and the runner copies fixtures into a scratch host's app/.
 * Kept apart from the QanB* test fixtures so the specs' records and the screenshot content never
 * mix.
 */
class QanShowcaseAdmin extends ModelAdmin
{
    private static $url_segment = 'events';

    private static $menu_title = 'Events';

    private static $menu_icon_class = 'font-icon-calendar';

    # Keyed managed_models, so the URL segment is "events" rather than the namespaced class name.
    private static $managed_models = [
        'events' => [
            'dataClass' => QanShowcaseEvent::class,
            'title' => 'Events',
        ],
    ];
}
