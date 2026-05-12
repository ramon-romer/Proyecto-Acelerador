<?php
declare(strict_types=1);

namespace Acelerador\PanelBackend\Domain\Interfaces;

use Acelerador\PanelBackend\Domain\Entities\TutorContext;

interface TutorContextProviderInterface
{
    public function requireTutor(): TutorContext;
}

