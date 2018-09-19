<?php
declare(strict_types = 1);

namespace TYPO3\CMS\Core\Routing\Aspect;

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
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;

class AspectFactory extends AbstractAspectFactory
{
    /**
     * @var Site
     */
    protected $site;

    /**
     * @var SiteLanguage
     */
    protected $siteLanguage;

    public function __construct(Site $site, SiteLanguage $siteLanguage)
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
            SlugMapper::class,
            StaticValueMapper::class,
            LocaleModifier::class,
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
     * @return Applicable
     */
    public function build(array $settings): Applicable
    {
        $type = (string)($settings['type'] ?? '');
        if (empty($type)) {
            throw new \LogicException(
                'Aspect type cannot be empty',
                1537298184
            );
        }
        if (!in_array($type, $this->builds(), true)) {
            throw new \LogicException(
                sprintf('Cannot build aspect %s', $type),
                1537277122
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
                1537277123
            );
        }
        return $this->enrich(
            call_user_func($callable, $settings)
        );
    }

    /**
     * @param array $settings
     * @return SlugMapper
     */
    protected function buildSlugMapper(array $settings): SlugMapper
    {
        $tableName = $settings['tableName'] ?? null;
        $routeFieldName = $settings['routeFieldName'] ?? null;
        $valueFieldName = $settings['valueFieldName'] ?? null;

        if (!is_string($tableName)) {
            throw new \LogicException('tableName must be string', 1537277133);
        }
        if (!is_string($routeFieldName)) {
            throw new \LogicException('routeFieldName name must be string', 1537277134);
        }
        if (!is_string($valueFieldName)) {
            throw new \LogicException('valueFieldName name must be string', 1537277135);
        }

        return new SlugMapper($tableName, $routeFieldName, $valueFieldName);
    }

    /**
     * @param array $settings
     * @return StaticValueMapper
     */
    protected function buildStaticValueMapper(array $settings): StaticValueMapper
    {
        $map = $settings['map'] ?? null;
        $localeMap = $settings['localeMap'] ?? null;

        if (!array($map)) {
            throw new \LogicException('map must be array', 1537277143);
        }
        if (!array($localeMap ?? [])) {
            throw new \LogicException('localeMap must be array', 1537277144);
        }

        return new StaticValueMapper($map, $localeMap);
    }

    /**
     * @param array $settings
     * @return LocaleModifier
     */
    protected function buildLocaleModifier(array $settings): LocaleModifier
    {
        $localeMap = $settings['localeMap'] ?? null;
        $default = $settings['default'] ?? null;

        if (!array($localeMap)) {
            throw new \LogicException('localeMap must be array', 1537277153);
        }
        if (!is_string($default ?? '')) {
            throw new \LogicException('default must be string', 1537277154);
        }

        return new LocaleModifier($localeMap, $default);
    }

    /**
     * @param Applicable $object
     * @param string $identifier|null
     * @return Applicable|mixed
     */
    protected function enrich(Applicable $object): Applicable
    {
        $uses = class_uses($object);
        if (in_array(SiteAwareTrait::class, $uses, true)) {
            $object->setSite($this->site);
        }
        if (in_array(SiteLanguageAwareTrait::class, $uses, true)) {
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
