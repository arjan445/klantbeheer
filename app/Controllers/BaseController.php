<?php

namespace App\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\HTTP\CLIRequest;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Exceptions\PageNotFoundException;
use Psr\Log\LoggerInterface;

abstract class BaseController extends Controller
{
    /** @var CLIRequest|IncomingRequest */
    protected $request;

    protected $helpers = ['form', 'url'];

    protected $session;

    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);

        $this->session = service('session');
    }

    /**
     * Pagina binnen de layout.
     */
    protected function page(string $view, array $data = [], string $title = ''): string
    {
        $data['title'] = $title;

        return view('templates/header', ['title' => $title])
            . view($view, $data)
            . view('templates/footer');
    }

    /** Record ophalen of 404 */
    protected function findOr404(string $modelClass, $id): array
    {
        $row = model($modelClass)->find($id);
        if ($row === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $row;
    }

    protected function back(string $message, string $type = 'message', ?string $to = null): RedirectResponse
    {
        $this->session->setFlashdata($type, $message);

        return redirect()->to($to ?? previous_url());
    }

    /** Lege strings naar null (voor optionele velden) */
    protected function nullify(array $data): array
    {
        return array_map(static fn ($v) => is_string($v) && trim($v) === '' ? null : (is_string($v) ? trim($v) : $v), $data);
    }
}
