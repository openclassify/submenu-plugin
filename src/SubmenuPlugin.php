<?php namespace Visiosoft\SubmenuPlugin;

use Anomaly\Streams\Platform\Addon\Plugin\Plugin;
use Visiosoft\SubmenuPlugin\Commands\GetInteractiveMenus;
use Visiosoft\SubmenuPlugin\Commands\GetSections;
use Visiosoft\SubmenuPlugin\Commands\GetSubMenus;

class SubmenuPlugin extends Plugin
{
    public function getFunctions()
    {
        return [
            new \Twig\TwigFunction(
                'getSubmenus',
                function ($namespace) {
                    return $this->dispatchSync(new GetSubMenus($namespace));
                }
            ),
            new \Twig\TwigFunction(
                'getInteractiveMenus',
                function () {
                    return $this->dispatchSync(new GetInteractiveMenus());
                }
            ),
            new \Twig\TwigFunction(
                'getSections',
                function ($namespace) {
                    return $this->dispatchSync(new GetSections($namespace));
                }
            )
        ];
    }
}
