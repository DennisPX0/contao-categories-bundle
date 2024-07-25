<?php

/*
 * Copyright (c) 2021 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\CategoriesBundle\Model;

use Contao\Model;
use Contao\Model\Collection;

/**
 * @property int    $id
 * @property int    $tstamp
 * @property int    $category
 * @property string $parentTable
 * @property int    $entity
 * @property string $categoryField
 *
 * @method static CategoryAssociationModel[]|Collection|null findBy($strColumn, $varValue, array $arrOptions = array())
 */
class CategoryAssociationModel extends Model
{
    protected static $strTable = 'tl_category_association';
}
