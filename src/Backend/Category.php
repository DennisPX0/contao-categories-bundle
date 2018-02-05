<?php

/*
 * Copyright (c) 2017 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0+
 */

namespace HeimrichHannot\CategoriesBundle\Backend;

use Contao\Backend;
use Contao\DataContainer;
use Contao\Image;
use Contao\StringUtil;
use HeimrichHannot\CategoriesBundle\Model\CategoryModel;
use HeimrichHannot\Haste\Dca\General;
use HeimrichHannot\Haste\Util\Container;

class Category extends Backend
{
    const PRIMARY_CATEGORY_SUFFIX = '_primary';

    protected static $defaultPrimaryCategorySet = false;

    /**
     * Shorthand function for adding a single category field to your dca.
     *
     * @param array  $evalOverride
     * @param string $label
     *
     * @return array
     */
    public static function getCategoryFieldDca($evalOverride = null, $label = null)
    {
        \System::loadLanguageFile('tl_category');

        $label = $label ?: $GLOBALS['TL_LANG']['tl_category']['category'];
        $eval  = [
            'tl_class'        => 'w50 autoheight',
            'mandatory'       => true,
            'fieldType'       => 'radio',
            'isCategoryField' => true,
        ];

        if (is_array($evalOverride)) {
            $eval = array_merge($eval, $evalOverride);
        }

        return [
            'label'         => &$label,
            'exclude'       => true,
            'filter'        => true,
            'inputType'     => 'categoryTree',
            'foreignKey'    => 'tl_category.title',
            'load_callback' => [['HeimrichHannot\CategoriesBundle\Backend\Category', 'loadCategoriesFromAssociations']],
            'save_callback' => [['HeimrichHannot\CategoriesBundle\Backend\Category', 'storeToCategoryAssociations']],
            'eval'          => $eval,
            'sql'           => "int(10) unsigned NOT NULL default '0'",
        ];
    }

    /**
     * Shorthand function for adding a multiple categories field to your dca.
     *
     * @param string $table
     * @param string $name
     * @param array  $evalOverride
     * @param string $label
     */
    public static function addMultipleCategoriesFieldToDca($table, $name, $evalOverride = null, $label = null)
    {
        \System::loadLanguageFile('tl_category');

        $label = $label ?: $GLOBALS['TL_LANG']['tl_category']['categories'];
        $eval  = [
            'tl_class'             => 'w50 autoheight clr',
            'mandatory'            => true,
            'multiple'             => true,
            'fieldType'            => 'checkbox',
            'addPrimaryCategory'   => true,
            'forcePrimaryCategory' => true,
            'isCategoryField'      => true,
        ];

        if (is_array($evalOverride)) {
            $eval = array_merge($eval, $evalOverride);
        }

        \Controller::loadDataContainer($table);

        $GLOBALS['TL_DCA'][$table]['fields'][$name] = [
            'label'         => &$label,
            'exclude'       => true,
            'filter'        => true,
            'inputType'     => 'categoryTree',
            'foreignKey'    => 'tl_category.title',
            'load_callback' => [['HeimrichHannot\CategoriesBundle\Backend\Category', 'loadCategoriesFromAssociations']],
            'save_callback' => [
                ['HeimrichHannot\CategoriesBundle\Backend\Category', 'storePrimaryCategory'],
                ['HeimrichHannot\CategoriesBundle\Backend\Category', 'storeToCategoryAssociations'],
            ],
            'eval'          => $eval,
            'sql'           => 'blob NULL',
        ];

        if ($eval['addPrimaryCategory']) {
            $GLOBALS['TL_DCA'][$table]['fields'][$name . static::PRIMARY_CATEGORY_SUFFIX] = [
                'sql' => "int(10) unsigned NOT NULL default '0'",
            ];
        }
    }

    public static function deleteCachedPropertyValuesByCategoryAndProperty($value, DataContainer $dc)
    {
        if (null !== ($instance = General::getModelInstance($dc->table, $dc->id))) {
            $valueOld = $instance->{$dc->field};

            if ($value != $valueOld) {
                \System::getContainer()->get('huh.categories.property_cache_manager')->delete([
                    'category=?',
                    'property=?',
                ], [
                    'tl_category' === $dc->table ? $instance->id : $instance->pid,
                    $dc->field,
                ]);
            }
        }

        return $value;
    }

    public static function deleteCachedPropertyValuesByCategoryAndPropertyBool($value, DataContainer $dc)
    {
        if (null !== ($instance = General::getModelInstance($dc->table, $dc->id))) {
            // compute name of the field being overridden
            $overrideField = lcfirst(str_replace('override', '', $dc->field));

            \System::getContainer()->get('huh.categories.property_cache_manager')->delete([
                'category=?',
                'property=?',
            ], [
                'tl_category' === $dc->table ? $instance->id : $instance->pid,
                $overrideField,
            ]);
        }

        return $value;
    }

    public function getPrimarizeOperation($row, $href, $label, $title, $icon)
    {
        $checked = '';

        if (!($field = \Input::get('category_field')) || !($table = \Input::get('category_table'))) {
            return '';
        }

        \Controller::loadDataContainer($table);

        $dcaEval = $GLOBALS['TL_DCA'][$table]['fields'][$field]['eval'];

        if (!$dcaEval['addPrimaryCategory']) {
            return '';
        }

        $primaryCategory = \Input::get('primaryCategory');

        $isParentCategory              = \System::getContainer()->get('huh.categories.manager')->hasChildren($row['id']);
        $checkAsDefaultPrimaryCategory = (!$isParentCategory || !$dcaEval['parentsUnselectable']) && !$primaryCategory && $dcaEval['forcePrimaryCategory'] && !static::$defaultPrimaryCategorySet;

        if ($checkAsDefaultPrimaryCategory || $row['id'] === \Input::get('primaryCategory')) {
            static::$defaultPrimaryCategorySet = true;
            $checked                           = ' checked';
        }

        return '<input type="radio" name="primaryCategory" data-id="' . $row['id'] . '" id="primaryCategory_' . $row['id'] . '" value="primary_' . $row['id'] . '"' . $checked . '>' . '<label style="margin-right: 6px" for="primaryCategory_' . $row['id'] . '" title="' . $title . '" class="primarize">' . '<span class="icon primarized">' . \Image::getHtml('bundles/categories/img/icon_primarized.png')
               . '</span>' . '<span class="icon unprimarized">' . \Image::getHtml('bundles/categories/img/icon_unprimarized.png') . '</span>' . '</label>';
    }

    /**
     * Automatically add overridable fields to the dca (including palettes, ...).
     */
    public static function addOverridableFieldSelectors()
    {
        $dca = &$GLOBALS['TL_DCA']['tl_category'];

        // add overridable fields
        foreach ($dca['fields'] as $field => $data) {
            if ($data['eval']['overridable']) {
                $overrideFieldName = 'override' . ucfirst($field);

                // boolean field
                $dca['fields'][$overrideFieldName] = [
                    'label'         => &$GLOBALS['TL_LANG']['tl_category'][$overrideFieldName],
                    'exclude'       => true,
                    'inputType'     => 'checkbox',
                    'save_callback' => [['HeimrichHannot\CategoriesBundle\Backend\Category', 'deleteCachedPropertyValuesByCategoryAndPropertyBool']],
                    'eval'          => ['tl_class' => 'w50', 'submitOnChange' => true],
                    'sql'           => "char(1) NOT NULL default ''",
                ];

                // selector
                $dca['palettes']['__selector__'][] = $overrideFieldName;

                // subpalette
                $dca['subpalettes'][$overrideFieldName] = $field;
            }
        }
    }

    /**
     * @param DataContainer $dc
     */
    public function modifyPalette(DataContainer $dc)
    {
        $category = CategoryModel::findByPk($dc->id);
        $dca      = &$GLOBALS['TL_DCA']['tl_category'];

        if ($category) {
            if ($category->pid) {
                $dca['palettes']['default'] = str_replace('jumpTo', 'overrideJumpTo', $dca['palettes']['default']);
            }
        }

        // hide primarize operation if not in picker context
        // show only in picker
        if (!\Input::get('picker')) {
            unset($dca['list']['operations']['primarize']);
        }
    }

    /**
     * @param mixed         $value
     * @param DataContainer $dc
     */
    public function storePrimaryCategory($value, DataContainer $dc)
    {
        if ($primaryCategory = \Input::post($dc->field . static::PRIMARY_CATEGORY_SUFFIX)) {
            if (null !== ($entity = General::getModelInstance($dc->table, $dc->id))) {
                $entity->{$dc->field . static::PRIMARY_CATEGORY_SUFFIX} = $primaryCategory;
                $entity->save();
            }
        }

        return $value;
    }

    /**
     * @param mixed         $value
     * @param DataContainer $dc
     */
    public function storeToCategoryAssociations($value, DataContainer $dc)
    {
        switch ($GLOBALS['TL_DCA'][$dc->table]['fields'][$dc->field]['eval']['fieldType']) {
            case 'radio':
                \System::getContainer()->get('huh.categories.manager')->createAssociations($dc->id, $dc->field, $dc->table, [$value]);
                break;
            case 'checkbox':
                \System::getContainer()->get('huh.categories.manager')->createAssociations($dc->id, $dc->field, $dc->table, StringUtil::deserialize($value, true));
                break;
        }

        return $value;
    }

    /**
     * @param mixed         $value
     * @param DataContainer $dc
     *
     * @return array|null
     */
    public function loadCategoriesFromAssociations($value, DataContainer $dc)
    {
        if (!$dc->id || !$dc->field) {
            return $value;
        }

        $categories = \System::getContainer()->get('huh.categories.manager')->findByEntityAndCategoryFieldAndTable($dc->id, $dc->field, $dc->table);

        if (null === $categories) {
            return null;
        }

        $categoryIds = $categories->fetchEach('id');

        if (!empty($categoryIds)) {
            switch ($GLOBALS['TL_DCA'][$dc->table]['fields'][$dc->field]['eval']['fieldType']) {
                case 'radio':
                    return $categoryIds[0];
                case 'checkbox':
                    return $categoryIds;
            }
        }

        return null;
    }

    /**
     * @param string        $varValue
     * @param DataContainer $dc
     *
     * @return string
     */
    public static function generateAlias($varValue, DataContainer $dc)
    {
        if (null === ($category = CategoryModel::findByPk($dc->id))) {
            return '';
        }

        $title = $category->title ?: $dc->activeRecord->title;

        return General::generateAlias($varValue, $dc->id, 'tl_category', $title);
    }

    public function checkPermission()
    {
        $user = \BackendUser::getInstance();

        if (!$user->isAdmin && !$user->hasAccess('manage', 'categories')) {
            \Controller::redirect('contao/main.php?act=error');
        }
    }

    /**
     * Return the paste category button.
     *
     * @param \DataContainer
     * @param array
     * @param string
     * @param bool
     * @param array
     *
     * @return string
     */
    public function pasteCategory(DataContainer $dc, $row, $table, $cr, $arrClipboard = null)
    {
        $disablePA = false;
        $disablePI = false;

        // Disable all buttons if there is a circular reference
        if (false !== $arrClipboard && ('cut' === $arrClipboard['mode'] && (1 === $cr || $arrClipboard['id'] === $row['id']) || 'cutAll' === $arrClipboard['mode'] && (1 === $cr || in_array($row['id'], $arrClipboard['id'], true)))) {
            $disablePA = true;
            $disablePI = true;
        }

        $return = '';

        // Return the buttons
        $imagePasteAfter = Image::getHtml('pasteafter.gif', sprintf($GLOBALS['TL_LANG'][$table]['pasteafter'][1], $row['id']));
        $imagePasteInto  = Image::getHtml('pasteinto.gif', sprintf($GLOBALS['TL_LANG'][$table]['pasteinto'][1], $row['id']));

        if ($row['id'] > 0) {
            $return = $disablePA ? Image::getHtml('pasteafter_.gif') . ' ' : '<a href="' . \Controller::addToUrl('act=' . $arrClipboard['mode'] . '&mode=1&rt=' . \RequestToken::get() . '&pid=' . $row['id'] . (!is_array($arrClipboard['id']) ? '&id=' . $arrClipboard['id'] : '')) . '" title="' . specialchars(sprintf($GLOBALS['TL_LANG'][$table]['pasteafter'][1], $row['id']))
                                                                             . '" onclick="Backend.getScrollOffset()">' . $imagePasteAfter . '</a> ';
        }

        return $return . ($disablePI
                ? Image::getHtml('pasteinto_.gif') . ' ' : '<a href="' . \Controller::addToUrl('act=' . $arrClipboard['mode'] . '&mode=2&rt=' . \RequestToken::get() . '&pid=' . $row['id'] . (!is_array($arrClipboard['id']) ? '&id=' . $arrClipboard['id'] : '')) . '" title="' . specialchars(sprintf($GLOBALS['TL_LANG'][$table]['pasteinto'][1], $row['id'])) . '" onclick="Backend.getScrollOffset()">'
                                                           . $imagePasteInto . '</a> ');
    }

    /**
     * @param array
     * @param string
     * @param object
     * @param string
     *
     * @return string
     */
    public function generateLabel($row, $label, $dca, $attributes)
    {
        if (isset($row['frontendTitle']) && $row['frontendTitle']) {
            $label .= '<span style="padding-left:3px;color:#b3b3b3;">[' . $row['frontendTitle'] . ']</span>';
        }

        if ('edit' !== Container::getGet('act') && null !== (\System::getContainer())->get('huh.categories.config_manager')->findBy(['tl_category_config.pid=?'], [$row['id']])) {
            $label .= '<span style="padding-left:3px;color:#b3b3b3;">– ' . $GLOBALS['TL_LANG']['MSC']['categoriesBundle']['configsAvailable'] . '</span>';
        }

        return \Image::getHtml('iconPLAIN.gif', '', $attributes) . ' ' . $label;
    }

    /**
     * Shorthand function for adding a category filter list field to your dca.
     *
     * @param array  $evalOverride
     * @param string $label
     *
     * @return array
     */
    public static function getCategoryFilterListFieldDca($evalOverride = null, $label = null)
    {
        \System::loadLanguageFile('tl_category');

        $label = $label ?: $GLOBALS['TL_LANG']['tl_category']['categoryFilterList'];
        $eval  = [
            'tl_class' => 'w50 autoheight',
        ];

        if (is_array($evalOverride)) {
            $eval = array_merge($eval, $evalOverride);
        }

        return [
            'label'     => $label,
            'exclude'   => true,
            'inputType' => 'checkbox',
            'eval'      => $eval,
            'sql'       => "char(1) NOT NULL default ''",
        ];
    }

    /**
     * Get the parameter name
     *
     * @param int $rootId
     *
     * @return string
     */
    public static function getUrlParameterName($rootId = null)
    {
        if (!$rootId) {
            global $objPage;
            $rootId = $objPage->rootId;
        }
        if (!$rootId) {
            return '';
        }
        $rootPage = \PageModel::findByPk($rootId);
        if ($rootPage === null) {
            return '';
        }

        return $rootPage->categoriesParam ?: 'category';
    }
}
