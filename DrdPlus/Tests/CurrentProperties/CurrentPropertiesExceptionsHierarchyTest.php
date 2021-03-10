<?php declare(strict_types = 1);

namespace DrdPlus\Tests\CurrentProperties;

use Granam\Tests\ExceptionsHierarchy\Exceptions\AbstractExceptionsHierarchyTest;

class CurrentPropertiesExceptionsHierarchyTest extends AbstractExceptionsHierarchyTest
{
    /**
     * @return string
     */
    protected function getTestedNamespace()
    {
        return $this->getRootNamespace();
    }

    /**
     * @return string
     */
    protected function getRootNamespace()
    {
        return str_replace('\Tests', '', __NAMESPACE__);
    }

}
