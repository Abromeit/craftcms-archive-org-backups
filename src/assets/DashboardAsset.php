<?php

declare(strict_types=1);

namespace abromeit\archiveorgbackups\assets;

use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;

final class DashboardAsset extends AssetBundle
{
    public function init(): void
    {
        $this->sourcePath = __DIR__ . '/../web/assets';
        $this->depends = [CpAsset::class];
        $this->css = ['dashboard.css'];
        $this->js = ['dashboard.js'];

        parent::init();
    }
}
