<?php

namespace Restruct\QanBrowser;

use SilverStripe\Admin\ModelAdmin;

/**
 * BROWSER-TEST FIXTURE ONLY - the CMS screen the specs open: /admin/qan-browser/owners
 * (see QanBTag for why this never loads in a real install).
 */
class QanBAdmin extends ModelAdmin
{
    private static $url_segment = 'qan-browser';

    private static $menu_title = 'QuickAddNew browser test';

    # Keyed managed_models (SS5 and SS6): 'owners' becomes the URL segment, so specs need not
    # spell out the sanitised namespaced class name.
    private static $managed_models = [
        'owners' => [
            'dataClass' => QanBOwner::class,
            'title' => 'Owners',
        ],
    ];
}
