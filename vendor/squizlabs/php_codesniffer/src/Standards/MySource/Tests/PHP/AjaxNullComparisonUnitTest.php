<?php

/**
 * Unit test class for the AjaxNullComparison sniff.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace MolliePrefix\PHP_CodeSniffer\Standards\MySource\Tests\PHP;

use MolliePrefix\PHP_CodeSniffer\Tests\Standards\AbstractSniffUnitTest;
class AjaxNullComparisonUnitTest extends \MolliePrefix\PHP_CodeSniffer\Tests\Standards\AbstractSniffUnitTest
{
    /**
     * Returns the lines where errors should occur.
     *
     * The key of the array should represent the line number and the value
     * should represent the number of errors that should occur on that line.
     *
     * @return array<int, int>
     */
    public function getErrorList()
    {
        return [];
    }
    //end getErrorList()
    /**
     * Returns the lines where warnings should occur.
     *
     * The key of the array should represent the line number and the value
     * should represent the number of warnings that should occur on that line.
     *
     * @return array<int, int>
     */
    public function getWarningList()
    {
        return [41 => 1, 53 => 1, 64 => 1, 77 => 1, 92 => 1, 122 => 1];
    }
    //end getWarningList()
}
//end class
