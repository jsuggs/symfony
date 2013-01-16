<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Exception;

class UnmappedTypeException extends Exception
{
    public function __construct($value, array $map)
    {
        parent::__construct(sprintf('Unexpected type value "%s", valid value(s) are %s', $value, implode(', ', array_keys($map))));
    }
}
