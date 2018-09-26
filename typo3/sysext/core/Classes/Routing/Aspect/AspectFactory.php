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
use TYPO3\CMS\Core\Site\Entity\SiteInterface;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;

class AspectFactory implements Buildable
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
            PersistedAliasMapper::class,
            PersistedPatternMapper::class,
            StaticValueMapper::class,
            StaticRangeMapper::class,
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
     * @return PersistedAliasMapper
     */
    protected function buildPersistedAliasMapper(array $settings): PersistedAliasMapper
    {
        $tableName = $settings['tableName'] ?? null;
        $routeFieldName = $settings['routeFieldName'] ?? null;
        $valueFieldName = $settings['valueFieldName'] ?? null;
        $routeValuePrefix = $settings['routeValuePrefix'] ?? '';

        if (!is_string($tableName)) {
            throw new \LogicException('tableName must be string', 1537277133);
        }
        if (!is_string($routeFieldName)) {
            throw new \LogicException('routeFieldName name must be string', 1537277134);
        }
        if (!is_string($valueFieldName)) {
            throw new \LogicException('valueFieldName name must be string', 1537277135);
        }
        if (!is_string($routeValuePrefix) || strlen($routeValuePrefix) > 1) {
            throw new \LogicException('$routeValuePrefix name must be string with one character', 1537277136);
        }

        return new PersistedAliasMapper($tableName, $routeFieldName, $valueFieldName, $routeValuePrefix);
    }

    /**
     * @param array $settings
     * @return PersistedPatternMapper
     */
    protected function buildPersistedPatternMapper(array $settings): PersistedPatternMapper
    {
        $tableName = $settings['tableName'] ?? null;
        $routeFieldPattern = $settings['routeFieldPattern'] ?? null;
        $routeFieldResult = $settings['routeFieldResult'] ?? null;

        if (!is_string($tableName)) {
            throw new \LogicException('tableName must be string', 1537277173);
        }
        if (!is_string($routeFieldPattern)) {
            throw new \LogicException('routeFieldPattern name must be string', 1537277174);
        }
        if (!is_string($routeFieldResult)) {
            throw new \LogicException('routeFieldResult name must be string', 1537277175);
        }

        return new PersistedPatternMapper($tableName, $routeFieldPattern, $routeFieldResult);
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
     * @return StaticRangeMapper
     */
    protected function buildStaticRangeMapper(array $settings): StaticRangeMapper
    {
        $start = $settings['start'] ?? null;
        $end = $settings['end'] ?? null;

        if (!is_string($start)) {
            throw new \LogicException('start must be string', 1537277163);
        }
        if (!is_string($end)) {
            throw new \LogicException('end must be string', 1537277164);
        }

        return new StaticRangeMapper($start, $end);
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
     * @return Applicable|mixed
     */
    protected function enrich(Applicable $object): Applicable
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
