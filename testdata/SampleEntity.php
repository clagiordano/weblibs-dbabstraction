<?php

namespace clagiordano\weblibs\dbabstraction\testdata;

use clagiordano\weblibs\dbabstraction\AbstractEntity;

/**
 * Class SampleEntity
 */
class SampleEntity extends AbstractEntity
{
    protected $allowedFields = ['id', 'text', 'description', 'timestamp'];
}
