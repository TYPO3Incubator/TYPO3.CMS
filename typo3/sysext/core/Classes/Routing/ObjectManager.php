<?php
declare(strict_types = 1);

namespace TYPO3\CMS\Core\Routing;

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

use TYPO3\CMS\Core\Site\Entity\SiteInterface;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class ObjectManager
{
    /**
     * @var string[]
     */
    protected $enhancerFactoryClassNames = [];

    /**
     * @var string[]
     */
    protected $aspectFactoryClassNames = [
        Aspect\AspectFactory::class,
    ];

    /**
     * @var Enhancer\Buildable[][]
     */
    protected $enhancerFactories = [];

    /**
     * @var Aspect\Buildable[][]
     */
    protected $aspectFactories = [];

    /**
     * @param string $className
     */
    public function addEnhancerFactoryClassName(string $className)
    {
        $this->assertEnhancerBuildableType($className);
        if (in_array($className, $this->enhancerFactoryClassNames, true)) {
            return;
        }
        $this->enhancerFactoryClassNames[] = $className;
    }

    /**
     * @param SiteInterface $site
     * @param SiteLanguage $language
     * @return Enhancer\Buildable[]
     */
    public function getEnhancerFactories(SiteInterface $site, SiteLanguage $language): array
    {
        $identifier = $site->getIdentifier() . '::' . $language->getLanguageId();
        if (isset($this->enhancerFactories[$identifier])) {
            return $this->enhancerFactories[$identifier];
        }
        return $this->enhancerFactories[$identifier] = $this->buildInstances(
            $site,
            $language,
            $this->enhancerFactoryClassNames
        );
    }

    /**
     * @param string $className
     */
    public function addAspectFactoryClassName(string $className)
    {
        $this->assertAspectBuildableType($className);
        if (in_array($className, $this->aspectFactoryClassNames, true)) {
            return;
        }
        $this->aspectFactoryClassNames[] = $className;
    }

    /**
     * @param SiteInterface $site
     * @param SiteLanguage $language
     * @return Aspect\Buildable[]
     */
    public function getAspectFactories(SiteInterface $site, SiteLanguage $language): array
    {
        $identifier = $site->getIdentifier() . '::' . $language->getLanguageId();
        if (isset($this->aspectFactories[$identifier])) {
            return $this->aspectFactories[$identifier];
        }
        return $this->aspectFactories[$identifier] = $this->buildInstances(
            $site,
            $language,
            $this->aspectFactoryClassNames
        );
    }

    /**
     * @param SiteInterface $site
     * @param SiteLanguage $language
     * @param string[] $classNames
     * @return Enhancer\Buildable[]|Aspect\Buildable
     */
    protected function buildInstances(SiteInterface $site, SiteLanguage $language, array $classNames): array
    {
        return array_map(
            function (string $className) use ($site, $language) {
                return GeneralUtility::makeInstance(
                    $className,
                    $site,
                    $language
                );
            },
            $classNames
        );
    }

    /**
     * @param string $className
     */
    protected function assertEnhancerBuildableType(string $className)
    {
        if (!is_a($className, Enhancer\Buildable::class, true)) {
            throw new \LogicException(
                sprintf(
                    'Factory %s must implement %s',
                    $className,
                    Enhancer\Buildable::class
                ),
                1537805026
            );
        }
    }

    /**
     * @param string $className
     */
    protected function assertAspectBuildableType(string $className)
    {
        if (!is_a($className, Aspect\Buildable::class, true)) {
            throw new \LogicException(
                sprintf(
                    'Factory %s must implement %s',
                    $className,
                    Aspect\Buildable::class
                ),
                1537805027
            );
        }
    }
}
