<?php
namespace OCA\Recognize\Controller;

use OCA\Recognize\Service\TagManager;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

class AdminController extends Controller
{
    /**
     * @var \OCA\Recognize\Service\TagManager
     */
    private $tagManager;

    public function __construct($appName, IRequest $request, TagManager $tagManager)
    {
        parent::__construct($appName, $request);
        $this->tagManager = $tagManager;
    }

    public function reset() {
        $this->tagManager->resetClassifications();
        return new JSONResponse([]);
    }

    public function count() {
        $count = count($this->tagManager->findClassifiedFiles());
        return new JSONResponse(['count' => $count]);
    }
}
