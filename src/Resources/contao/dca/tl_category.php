<?php

\System::loadLanguageFile('tl_news_archive');

$GLOBALS['TL_DCA']['tl_category'] = [
    'config'   => [
        'label'             => $GLOBALS['TL_LANG']['tl_news_archive']['categories'][0],
        'dataContainer'     => 'Table',
        'enableVersioning'  => true,
        'onload_callback'   => [
            ['\HeimrichHannot\CategoriesBundle\Backend\Category', 'checkPermission']
        ],
        'onsubmit_callback' => [
            ['HeimrichHannot\Haste\Dca\General', 'setDateAdded'],
        ],
        'sql'               => [
            'keys' => [
                'id'    => 'primary',
                'pid'   => 'index',
                'alias' => 'index',
            ]
        ],
        'backlink'          => 'do=news'
    ],
    'list'     => [
        'sorting'           => [
            'mode'                  => 5,
            'icon'                  => 'system/modules/news_categories/assets/icon.png',
            'paste_button_callback' => ['\HeimrichHannot\CategoriesBundle\Backend\Category', 'pasteCategory'],
            'panelLayout'           => 'search'
        ],
        'label'             => [
            'fields'         => ['title', 'frontendTitle'],
            'format'         => '%s <span style="padding-left:3px;color:#b3b3b3;">[%s]</span>',
            'label_callback' => ['\HeimrichHannot\CategoriesBundle\Backend\Category', 'generateLabel']
        ],
        'global_operations' => [
            'toggleNodes' => [
                'label' => &$GLOBALS['TL_LANG']['MSC']['toggleAll'],
                'href'  => 'ptg=all',
                'class' => 'header_toggle'
            ],
            'all'         => [
                'label'      => &$GLOBALS['TL_LANG']['MSC']['all'],
                'href'       => 'act=select',
                'class'      => 'header_edit_all',
                'attributes' => 'onclick="Backend.getScrollOffset()" accesskey="e"'
            ],
        ],
        'operations'        => [
            'edit'       => [
                'label' => &$GLOBALS['TL_LANG']['tl_category']['edit'],
                'href'  => 'act=edit',
                'icon'  => 'edit.gif'
            ],
            'copy'       => [
                'label'      => &$GLOBALS['TL_LANG']['tl_category']['copy'],
                'href'       => 'act=paste&amp;mode=copy',
                'icon'       => 'copy.gif',
                'attributes' => 'onclick="Backend.getScrollOffset()"'
            ],
            'copyChilds' => [
                'label'      => &$GLOBALS['TL_LANG']['tl_category']['copyChilds'],
                'href'       => 'act=paste&amp;mode=copy&amp;childs=1',
                'icon'       => 'copychilds.gif',
                'attributes' => 'onclick="Backend.getScrollOffset()"'
            ],
            'cut'        => [
                'label'      => &$GLOBALS['TL_LANG']['tl_category']['cut'],
                'href'       => 'act=paste&amp;mode=cut',
                'icon'       => 'cut.gif',
                'attributes' => 'onclick="Backend.getScrollOffset()"'
            ],
            'delete'     => [
                'label'      => &$GLOBALS['TL_LANG']['tl_category']['delete'],
                'href'       => 'act=delete',
                'icon'       => 'delete.gif',
                'attributes' => 'onclick="if(!confirm(\'' . $GLOBALS['TL_LANG']['MSC']['deleteConfirm'] . '\'))return false;Backend.getScrollOffset()"'
            ],
            'show'       => [
                'label' => &$GLOBALS['TL_LANG']['tl_category']['show'],
                'href'  => 'act=show',
                'icon'  => 'show.gif'
            ],
            'toggle'     => [
                'label'           => &$GLOBALS['TL_LANG']['tl_category']['toggle'],
                'icon'            => 'visible.gif',
                'attributes'      => 'onclick="Backend.getScrollOffset();return AjaxRequest.toggleVisibility(this,%s)"',
                'button_callback' => ['\HeimrichHannot\CategoriesBundle\Backend\Category', 'toggleIcon']
            ]
        ]
    ],
    'palettes' => [
        '__selector__' => [
            'type'
        ],
        'default' => '{general_legend},type,title,alias,frontendTitle,cssClass;{redirect_legend:hide},jumpTo;{publish_legend},published',
        \HeimrichHannot\CategoriesBundle\Backend\Category::TYPE_ROOT =>
            '{general_legend},type,title,alias,frontendTitle,cssClass;{redirect_legend:hide},jumpTo;{publish_legend},published',
    ],
    'fields'   => [
        'id'            => [
            'sql' => "int(10) unsigned NOT NULL auto_increment"
        ],
        'pid'           => [
            'sql' => "int(10) unsigned NOT NULL default '0'"
        ],
        'sorting'       => [
            'sql' => "int(10) unsigned NOT NULL default '0'"
        ],
        'tstamp'        => [
            'sql' => "int(10) unsigned NOT NULL default '0'"
        ],
        'dateAdded'     => [
            'label'   => &$GLOBALS['TL_LANG']['MSC']['dateAdded'],
            'sorting' => true,
            'flag'    => 6,
            'eval'    => ['rgxp' => 'datim', 'doNotCopy' => true],
            'sql'     => "int(10) unsigned NOT NULL default '0'"
        ],
        'type'          => [
            'label'     => &$GLOBALS['TL_LANG']['tl_category']['type'],
            'exclude'   => true,
            'filter'    => true,
            'inputType' => 'select',
            'options'   => \HeimrichHannot\CategoriesBundle\Backend\Category::TYPES,
            'reference' => &$GLOBALS['TL_LANG']['tl_category']['reference'],
            'eval'      => ['tl_class' => 'w50', 'mandatory' => true, 'submitOnChange' => true],
            'sql'       => "varchar(16) NOT NULL default 'default'"
        ],
        'title'         => [
            'label'     => &$GLOBALS['TL_LANG']['tl_category']['title'],
            'exclude'   => true,
            'search'    => true,
            'inputType' => 'text',
            'eval'      => ['mandatory' => true, 'maxlength' => 255, 'tl_class' => 'w50 clr'],
            'sql'       => "varchar(255) NOT NULL default ''"
        ],
        'frontendTitle' => [
            'label'     => &$GLOBALS['TL_LANG']['tl_category']['frontendTitle'],
            'exclude'   => true,
            'search'    => true,
            'inputType' => 'text',
            'eval'      => ['maxlength' => 255, 'tl_class' => 'w50'],
            'sql'       => "varchar(255) NOT NULL default ''"
        ],
        'alias'         => [
            'label'         => &$GLOBALS['TL_LANG']['tl_category']['alias'],
            'exclude'       => true,
            'search'        => true,
            'inputType'     => 'text',
            'eval'          => ['rgxp' => 'alias', 'unique' => true, 'spaceToUnderscore' => true, 'maxlength' => 128, 'tl_class' => 'w50'],
            'save_callback' => [
                ['\HeimrichHannot\CategoriesBundle\Backend\Category', 'generateAlias']
            ],
            'sql'           => "varbinary(128) NOT NULL default ''"
        ],
        'cssClass'      => [
            'label'     => &$GLOBALS['TL_LANG']['tl_category']['cssClass'],
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => ['maxlength' => 128, 'tl_class' => 'w50'],
            'sql'       => "varchar(128) NOT NULL default ''",
        ],
        'jumpTo'        => [
            'label'     => &$GLOBALS['TL_LANG']['tl_category']['jumpTo'],
            'exclude'   => true,
            'inputType' => 'pageTree',
            'eval'      => ['fieldType' => 'radio'],
            'sql'       => "int(10) unsigned NOT NULL default '0'",
            'relation'  => ['type' => 'hasOne', 'load' => 'eager', 'table' => 'tl_page']
        ],
        'published'     => [
            'label'     => &$GLOBALS['TL_LANG']['tl_category']['published'],
            'exclude'   => true,
            'inputType' => 'checkbox',
            'sql'       => "char(1) NOT NULL default ''"
        ]
    ]
];