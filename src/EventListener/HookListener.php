<?php

/*
 * Copyright (c) 2017 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0+
 */

namespace HeimrichHannot\CategoriesBundle\EventListener;

use Contao\CoreBundle\Exception\ResponseException;
use Contao\Database;
use Contao\DataContainer;
use Contao\System;
use HeimrichHannot\CategoriesBundle\Widget\CategoryTree;
use HeimrichHannot\Haste\Util\Container;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Wa72\HtmlPageDom\HtmlPageCrawler;

class HookListener
{
    public function adjustCategoryTree($buffer, $template)
    {
        if (!\Input::get('picker') || !($field = \Input::get('category_field')) || !($table = \Input::get('category_table'))) {
            return $buffer;
        }

        $dcaEval = $GLOBALS['TL_DCA'][$table]['fields'][$field]['eval'];

        // hide unselectable checkboxes
        if ($dcaEval['parentsUnselectable']) {
            $objNode = new HtmlPageCrawler($buffer);

            $objNode->filter('.tree_view input[name="picker[]"]')->each(
                function ($objElement) {
                    $category = $objElement->getAttribute('value');

                    if (\System::getContainer()->get('huh.categories.manager')->hasChildren($category)) {
                        $objElement->replaceWith('<div class="dummy" style="display: inline-block; width: 22px; height: 13px;"></div>');
                    }
                }
            );

            $objNode->filter('.tree_view input[name="primaryCategory"]')->each(
                function ($objElement) {
                    $category = $objElement->getAttribute('data-id');

                    if (\System::getContainer()->get('huh.categories.manager')->hasChildren($category)) {
                        $objElement->removeAttribute('checked');

                        $objElement->siblings()->first()->attr('style', 'opacity: 0 !important');
                    }
                }
            );

            return $objNode->saveHTML();
        }

        return $buffer;
    }

    public function reloadCategoryTree($action, DataContainer $dc)
    {
        switch ($action) {
            case 'reloadCategoryTree':
                $id = \Input::get('id');
                $field = $dc->inputName = \Input::post('name');

                // Handle the keys in "edit multiple" mode
                if ('editAll' === \Input::get('act')) {
                    $id = preg_replace('/.*_([0-9a-zA-Z]+)$/', '$1', $field);
                    $field = preg_replace('/(.*)_[0-9a-zA-Z]+$/', '$1', $field);
                }

                $dc->field = $field;

                // The field does not exist
                if (!isset($GLOBALS['TL_DCA'][$dc->table]['fields'][$field])) {
                    Container::log('Field "'.$field.'" does not exist in DCA "'.$dc->table.'"', __METHOD__, TL_ERROR);
                    throw new BadRequestHttpException('Bad request');
                }

                $row = null;
                $value = null;

                // Load the value
                if ('overrideAll' !== \Input::get('act')) {
                    if ($GLOBALS['TL_DCA'][$dc->table]['config']['dataContainer'] === 'File') {
                        $value = \Config::get($field);
                    } elseif ($id > 0 && Database::getInstance()->tableExists($dc->table)) {
                        $row = Database::getInstance()->prepare('SELECT * FROM '.$dc->table.' WHERE id=?')
                            ->execute($id);

                        // The record does not exist
                        if ($row->numRows < 1) {
                            Container::log('A record with the ID "'.$id.'" does not exist in table "'.$dc->table.'"', __METHOD__, TL_ERROR);
                            throw new BadRequestHttpException('Bad request');
                        }

                        $value = $row->$field;
                        $dc->activeRecord = $row;
                    }
                }

                // Call the load_callback
                if (is_array($GLOBALS['TL_DCA'][$dc->table]['fields'][$field]['load_callback'])) {
                    foreach ($GLOBALS['TL_DCA'][$dc->table]['fields'][$field]['load_callback'] as $callback) {
                        if (is_array($callback)) {
                            $callbackObj = System::importStatic($callback[0]);
                            $value = $callbackObj->{$callback[1]}($value, $dc);
                        } elseif (is_callable($callback)) {
                            $value = $callback($value, $dc);
                        }
                    }
                }

                // Set the new value
                $value = \Input::post('value', true);
                $key = 'categoryTree';

                // Convert the selected values
                if ('' !== $value) {
                    $value = \StringUtil::trimsplit("\t", $value);

                    $value = serialize($value);
                }

                /** @var CategoryTree $strClass */
                $strClass = $GLOBALS['BE_FFL'][$key];

                /** @var CategoryTree $objWidget */
                $objWidget = new $strClass($strClass::getAttributesFromDca($GLOBALS['TL_DCA'][$dc->table]['fields'][$field], $dc->inputName, $value, $field, $dc->table, $dc));

                throw new ResponseException(new Response(static::replaceOldBePaths($objWidget->generate())));
        }
    }

    /**
     * Protected function taken from \Contao\Controller.
     */
    protected static function replaceOldBePaths($strContext)
    {
        $router = \System::getContainer()->get('router');

        $generate = function ($route) use ($router) {
            return substr($router->generate($route), strlen(\Environment::get('path')) + 1);
        };

        $arrMapper = [
            'contao/confirm.php' => $generate('contao_backend_confirm'),
            'contao/file.php' => $generate('contao_backend_file'),
            'contao/help.php' => $generate('contao_backend_help'),
            'contao/index.php' => $generate('contao_backend_login'),
            'contao/main.php' => $generate('contao_backend'),
            'contao/page.php' => $generate('contao_backend_page'),
            'contao/password.php' => $generate('contao_backend_password'),
            'contao/popup.php' => $generate('contao_backend_popup'),
            'contao/preview.php' => $generate('contao_backend_preview'),
            'contao/switch.php' => $generate('contao_backend_switch'),
        ];

        return str_replace(array_keys($arrMapper), array_values($arrMapper), $strContext);
    }
}
