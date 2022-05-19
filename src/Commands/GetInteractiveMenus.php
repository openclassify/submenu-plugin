<?php namespace Visiosoft\SubmenuPlugin\Commands;

use Anomaly\Streams\Platform\Addon\Module\Module;
use Anomaly\Streams\Platform\Addon\Module\ModuleCollection;
use Anomaly\Streams\Platform\Ui\Icon\Command\GetIcon;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Routing\UrlGenerator;
use Illuminate\Support\Str;
use Visiosoft\SubmenuPlugin\SubmenuCollection;

class GetInteractiveMenus
{
    use DispatchesJobs;

    public function handle(ModuleCollection $modules)
    {
        $remove_addons = [];
        $navigation = [];

        /* @var Module $module */
        foreach ($modules->enabled()->accessible() as $module) {
            if ($module->getNavigation()) {
                $navigation[$module->getSlug()] = $module;
            }
        }

        $navigation = array_map(
            function (Module $module) {
                return [
                    'breadcrumb' => $module->getName(),
                    'icon' => $module->getIcon(),
                    'title' => $module->getTitle(),
                    'slug' => $module->getNamespace(),
                    'href' => 'admin/' . $module->getSlug(),
                ];
            },
            $navigation
        );

        ksort($navigation);

        foreach ($navigation as $key => $module) {

            if ($key == 'dashboard') {

                $navigation = [$key => $module] + $navigation;

                break;
            }
        }

        foreach ($navigation as $index => $item) {
            $navigation[$index]['active'] = (!Str::contains(request()->url(), $item['href'])) ? false : true;

            $icon = $navigation[$index]['icon'];

            $navigation[$index]['icon'] = $this->dispatchNow(new GetIcon($icon));

            $module = app('addon.collection')->get($item['slug']);

            $menu = array();

            $sections = $this->buildSection($module);

            foreach ($sections as $section) {
                $menu[] = [
                    'title' => $section['title'],
                    'slug' => $section['slug'],
                    'active' => false,
                    'href' => $section['attributes']['href'],
                ];
            }


//            $items = app('module.collection')->installed();
//
//            $parent = $item['slug'];
//
//            $modules = $items->filter(
//                function ($addon) use ($parent) {
//                    return (isset($addon->parent) && $addon->parent == $parent);
//                }
//            );
//
//            if ($parent == "anomaly.module.settings") {
//                $modules = $items->filter(
//                    function ($addon) use ($parent) {
//                        return in_array($addon->slug, ['variables', 'system', 'redirects', 'repeaters']);
//                    }
//                );
//            }

//            $sub_menus = [];
//
//            foreach ($modules as $module) {
//
//                $remove_addons[] = $module->slug;
//
//                $links = new SubmenuCollection();
//
//                $sections = $this->buildSection($module);
//
//
//                foreach ($sections as $section) {
//
//                    if ($module->slug && in_array($module->slug, ['variables', 'system', 'redirects', 'repeaters'])) {
//                        $parent = 'anomaly.module.settings';
//                    } else {
//                        $parent = $module->parent;
//                    }
//
//                    $navigation = $this->checkSubMenuActive($navigation, $parent, $section['attributes']['href']);
//
//                    $links->add([
//                        'title' => $section['title'],
//                        'slug' => $section['slug'],
//                        'href' => $section['attributes']['href'],
//                    ]);
//                }
//
//                $sub_menus[$module->slug]['links'] = $links;
//                $sub_menus[$module->slug]['title'] = $module->namespace . "::addon.title";
//            }

//            $menu['sub_menus'] = $sub_menus;

            $navigation[$index]['sections'] = $menu;
        }
//
//        $navigation = array_filter(
//            $navigation,
//            function ($key) use ($remove_addons) {
//                return !in_array($key, $remove_addons);
//            },
//            ARRAY_FILTER_USE_KEY
//        );

        $navigation = $this->grouping($navigation);
        $navigation = $this->checkActive($navigation);

        return $navigation;
    }

    public function grouping($navigation)
    {
        $list = config('visiosoft.plugin.submenu::groups');
        $new_navigation = [];
        foreach ($list as $list_key => $item) {
            if (isset($navigation[$list_key])) {
                $addons = isset($new_navigation[$item]['addons']) ? $new_navigation[$item]['addons'] : array();
                $addons[$list_key] = $navigation[$list_key];

                $new_navigation[$item]['addons'] = $addons;
                $new_navigation[$item]['title'] = trans('visiosoft.plugin.submenu::group.' . $item);
                $new_navigation[$item]['active'] = false;
            }
        }

        return $new_navigation;
    }

    public function buildSection($module)
    {
        $sections = $module->getSections();

        /*
         * Loop over each section and make sense of the input
         * provided for the given module.
         */
        foreach ($sections as $slug => &$section) {

            /*
             * If the slug is not valid and the section
             * is a string then use the section as the slug.
             */
            if (is_numeric($slug) && is_string($section)) {
                $section = [
                    'slug' => $section,
                ];
            }

            /*
             * If the slug is a string and the title is not
             * set then use the slug as the slug.
             */
            if (is_string($slug) && !isset($section['slug'])) {
                $section['slug'] = $slug;
            }

            /*
             * Make sure we have attributes.
             */
            $section['attributes'] = array_get($section, 'attributes', []);

            /*
             * Move the HREF into attributes.
             */
            if (isset($section['href'])) {
                $section['attributes']['href'] = array_pull($section, 'href');
            }

            /*
             * Move all data-* keys
             * to attributes.
             */
            foreach ($section as $attribute => $value) {
                if (str_is('data-*', $attribute)) {
                    array_set($section, 'attributes.' . $attribute, array_pull($section, $attribute));
                }
            }

            /*
             * Move the data-href into the permalink.
             *
             * @deprecated as of v3.2
             */
            if (!isset($section['permalink']) && isset($section['attributes']['data-href'])) {
                $section['permalink'] = array_pull($section, 'attributes.data-href');
            }

            /*
             * Make sure the HREF and permalink are absolute.
             */
            if (
                isset($section['attributes']['href']) &&
                is_string($section['attributes']['href']) &&
                !starts_with($section['attributes']['href'], 'http')
            ) {
                $section['attributes']['href'] = url($section['attributes']['href']);
            }

            if (
                isset($section['permalink']) &&
                is_string($section['permalink']) &&
                !starts_with($section['permalink'], 'http')
            ) {
                $section['permalink'] = url($section['permalink']);
            }
        }

        $sections = $this->guessHref($module, $sections);
        $sections = $this->guessTitle($module, $sections);

        return $sections;
    }

    public function guessHref($module, $sections)
    {
        $url = app(UrlGenerator::class);

        foreach ($sections as $index => &$section) {

            // If HREF is set then skip it.
            if (isset($section['attributes']['href'])) {
                continue;
            }

            $href = $url->to('admin/' . $module->getSlug());

            if ($index !== 0 && $module->getSlug() !== $section['slug']) {
                $href .= '/' . $section['slug'];
            }

            $section['attributes']['href'] = $href;
        }

        return $sections;
    }

    public function guessTitle($module, $sections)
    {
        foreach ($sections as &$section) {

            // If title is set then skip it.
            if (isset($section['title'])) {
                continue;
            }

            $title = $module->getNamespace('section.' . $section['slug'] . '.title');

            if (!isset($section['title']) && trans()->has($title)) {
                $section['title'] = $title;
            }

            $title = $module->getNamespace('addon.section.' . $section['slug']);

            if (!isset($section['title']) && trans()->has($title)) {
                $section['title'] = $title;
            }

            if (!isset($section['title']) && config('streams::system.lazy_translations')) {
                $section['title'] = ucwords(humanize($section['slug']));
            }

            if (!isset($section['title'])) {
                $section['title'] = $title;
            }
        }

        return $sections;
    }

    function checkActive($navigation)
    {

        foreach ($navigation as $group_key => $group) {
            foreach ($group['addons'] as $addon_key => $addon) {
                foreach ($addon['sections'] as $section_key => $section) {
                    $is_active_section = (request()->url() === $section['href']) ? true : false;
                    $is_active = (!Str::contains(request()->url(), $section['href'])) ? false : true;
                    $navigation[$group_key]['addons'][$addon_key]['sections'][$section_key]['active'] = $is_active_section;
                    $navigation[$group_key]['addons'][$addon_key]['active'] = $navigation[$group_key]['addons'][$addon_key]['active'] ?: $is_active;
                    $navigation[$group_key]['active'] = $navigation[$group_key]['active'] ?: $is_active;
                }
            }
        }

//        dd($navigation);


//        $parent = explode('.', $parent);
//        $parent_slug = end($parent);
//
//
//        if ($is_active) {
//            $navigation[$parent_slug]['active'] = $is_active;
//        }

        return $navigation;
    }
}
