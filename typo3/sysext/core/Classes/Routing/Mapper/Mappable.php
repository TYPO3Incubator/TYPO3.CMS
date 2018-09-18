<?php
declare(strict_types = 1);

namespace TYPO3\CMS\Core\Routing\Mapper;

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

interface Mappable extends \Serializable
{
    // @todo Decide whether to put SiteContext to __constructor or to each method
    // (having it with each method of this interface would make it more explicit)

    /**
     * Regular `requirements`
     * @return string
     */
    public function getRegularExpression(): string;

    /**
     * Expression language condition
     * @return string
     */
    public function getCondition(): string;

    /**
     * @param string $value
     * @return string
     */
    public function generate(string $value): string;

    /**
     * @param string $value
     * @return string
     */
    public function resolve(string $value): string;
}
