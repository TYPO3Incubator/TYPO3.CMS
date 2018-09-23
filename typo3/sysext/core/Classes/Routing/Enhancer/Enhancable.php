<?php
declare(strict_types = 1);

namespace TYPO3\CMS\Core\Routing\Enhancer;

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

use Symfony\Component\Routing\RouteCollection;

interface Enhancable
{
    /**
     * Extends route collection with all routes. Used during URL resolving.
     *
     * @param RouteCollection $collection
     * @return void
     */
    public function enhance(RouteCollection $collection): void;

    /**
     * Extends route collection with routes that are relevant for given
     * parameters. Used during URL generation.
     *
     * @param RouteCollection $collection
     * @param array $parameters
     * @return void
     */
    public function addRoutesThatMeetTheRequirements(RouteCollection $collection, array $parameters): void;
}
