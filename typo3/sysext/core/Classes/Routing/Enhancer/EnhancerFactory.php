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

use TYPO3\CMS\Core\Routing\Traits\SiteAwareTrait;
use TYPO3\CMS\Core\Routing\Traits\SiteLanguageAwareTrait;
use TYPO3\CMS\Core\Site\Entity\SiteInterface;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;

class EnhancerFactory implements Buildable
{
    /**
     * @var SiteInterface
     */
    protected $site;

    /**
     * @var SiteLanguage
     */
    protected $siteLanguage;

    public function __construct(SiteInterface $site, SiteLanguage $siteLanguage)
    {
        $this->site = $site;
        $this->siteLanguage = $siteLanguage;
    }

    /**
     * @return string[]
     */
    public function builds(): array
    {
        $classNames = [
            PageTypeEnhancer::class,
            ExtbasePluginEnhancer::class,
            PluginEnhancer::class,
        ];
        // remove namespace prefix
        return array_merge(
            $classNames,
            array_map(
                [$this, 'removeNamespace'],
                $classNames
            )
        );
    }

    /**
     * @param array $settings
     * @return Enhancable
     */
    public function build(array $settings): Enhancable
    {
        $type = (string)($settings['type'] ?? '');
        if (empty($type)) {
            throw new \LogicException(
                'Enhancer type cannot be empty',
                1537298284
            );
        }
        if (!in_array($type, $this->builds(), true)) {
            throw new \LogicException(
                sprintf('Cannot build enhancer %s', $type),
                1537277222
            );
        }

        $callable = [$this, 'build' . $this->removeNamespace($type)];
        if (!is_callable($callable)) {
            throw new \LogicException(
                sprintf(
                    'Method %s::%s does not exist',
                    get_class($callable[0]),
                    $callable[1]
                ),
                1537277223
            );
        }
        return $this->enrich(
            call_user_func($callable, $settings)
        );
    }

    /**
     * @param array $settings
     * @return PluginEnhancer
     */
    protected function buildPluginEnhancer(array $settings): PluginEnhancer
    {
        // @todo Verify settings & pass to controller more explicitly
        return new PluginEnhancer($settings);
    }

    /**
     * @param array $settings
     * @return ExtbasePluginEnhancer
     */
    protected function buildExtbasePluginEnhancer(array $settings): ExtbasePluginEnhancer
    {
        // @todo Verify settings & pass to controller more explicitly
        return new ExtbasePluginEnhancer($settings);
    }

    /**
     * @param array $settings
     * @return PageTypeEnhancer
     */
    protected function buildPageTypeEnhancer(array $settings): PageTypeEnhancer
    {
        // @todo Verify settings & pass to controller more explicitly
        return new PageTypeEnhancer($settings);
    }

    /**
     * @param Enhancable $object
     * @return Enhancable|mixed
     */
    protected function enrich(Enhancable $object): Enhancable
    {
        $uses = class_uses($object);
        if (in_array(SiteAwareTrait::class, $uses, true)) {
            /** @var $object SiteAwareTrait */
            $object->setSite($this->site);
        }
        if (in_array(SiteLanguageAwareTrait::class, $uses, true)) {
            /** @var $object SiteLanguageAwareTrait */
            $object->setSiteLanguage($this->siteLanguage);
        }
        return $object;
    }

    /**
     * @param string $className
     * @return string
     */
    protected function removeNamespace(string $className): string
    {
        $namespaceLength = strlen(__NAMESPACE__ . '\\');
        if (strpos($className, __NAMESPACE__ . '\\') === 0) {
            return substr($className, $namespaceLength);
        }
        return $className;
    }
}
