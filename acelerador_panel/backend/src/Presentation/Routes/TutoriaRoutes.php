<?php
declare(strict_types=1);

namespace Acelerador\PanelBackend\Presentation\Routes;

use Acelerador\PanelBackend\Presentation\Controllers\TutoriaController;
use Acelerador\PanelBackend\Shared\Routing\Router;

final class TutoriaRoutes
{
    public static function register(Router $router, TutoriaController $controller): void
    {
        $router->add('POST', '/api/tutorias', [$controller, 'createTutoria']);
        $router->add('GET', '/api/tutorias/{tutoriaId}', [$controller, 'getTutoria']);
        $router->add('GET', '/api/tutorias/{tutoriaId}/profesores', [$controller, 'listProfesores']);
        $router->add('GET', '/api/tutorias/{tutoriaId}/profesores/{profesorId}', [$controller, 'getProfesorDetail']);
        $router->add('GET', '/api/tutorias/{tutoriaId}/matching', [$controller, 'getMatchingRecommendations']);
        $router->add('POST', '/api/tutorias/{tutoriaId}/profesores', [$controller, 'addProfesores']);
        $router->add('DELETE', '/api/tutorias/{tutoriaId}/profesores/{profesorId}', [$controller, 'removeProfesor']);
        $router->add('PUT', '/api/tutorias/{tutoriaId}/profesores', [$controller, 'syncProfesores']);
    }
}

