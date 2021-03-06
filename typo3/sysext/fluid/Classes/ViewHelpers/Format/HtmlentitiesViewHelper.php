<?php
namespace TYPO3\CMS\Fluid\ViewHelpers\Format;

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

use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\Traits\CompileWithContentArgumentAndRenderStatic;

/**
 * Applies :php:`htmlentities()` escaping to a value.
 * See https://www.php.net/manual/function.htmlentities.php.
 *
 * Examples
 * ========
 *
 * Default notation
 * ----------------
 *
 * ::
 *
 *    <f:format.htmlentities>{text}</f:format.htmlentities>
 *
 * Text with ``&`` ``"`` ``'`` ``<`` ``>`` ``*`` replaced by HTML entities :php:`htmlentities` applied.
 *
 * Inline notation
 * ---------------
 *
 * ::
 *
 *    {text -> f:format.htmlentities(encoding: 'ISO-8859-1')}
 *
 * Text with ``&`` ``"`` ``'`` ``<`` ``>`` ``*`` replaced by HTML entities :php:`htmlentities` applied.
 */
class HtmlentitiesViewHelper extends AbstractEncodingViewHelper
{
    use CompileWithContentArgumentAndRenderStatic;

    /**
     * Output gets encoded by this viewhelper
     *
     * @var bool
     */
    protected $escapeOutput = false;

    /**
     * This prevents double encoding as the whole output gets encoded at the end
     *
     * @var bool
     */
    protected $escapeChildren = false;

    /**
     * Initialize ViewHelper arguments
     */
    public function initializeArguments()
    {
        $this->registerArgument('value', 'string', 'string to format');
        $this->registerArgument('keepQuotes', 'bool', 'If TRUE, single and double quotes won\'t be replaced (sets ENT_NOQUOTES flag).', false, false);
        $this->registerArgument('encoding', 'string', '');
        $this->registerArgument('doubleEncode', 'bool', 'If FALSE existing html entities won\'t be encoded, the default is to convert everything.', false, true);
    }

    /**
     * Escapes special characters with their escaped counterparts as needed using PHPs htmlentities() function.
     *
     * @see https://www.php.net/manual/function.htmlentities.php
     * @param array $arguments
     * @param \Closure $renderChildrenClosure
     * @param RenderingContextInterface $renderingContext
     * @return mixed
     */
    public static function renderStatic(array $arguments, \Closure $renderChildrenClosure, RenderingContextInterface $renderingContext)
    {
        $value = $renderChildrenClosure();
        $encoding = $arguments['encoding'];
        $keepQuotes = $arguments['keepQuotes'];
        $doubleEncode = $arguments['doubleEncode'];

        if (!is_string($value) && !(is_object($value) && method_exists($value, '__toString'))) {
            return $value;
        }
        if ($encoding === null) {
            $encoding = self::resolveDefaultEncoding();
        }
        $flags = $keepQuotes ? ENT_NOQUOTES : ENT_QUOTES;
        return htmlentities((string)$value, $flags, $encoding, $doubleEncode);
    }
}
