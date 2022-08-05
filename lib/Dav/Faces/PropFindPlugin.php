<?php

namespace OCA\Recognize\Dav\Faces;

use Sabre\DAV\INode;
use Sabre\DAV\PropFind;
use Sabre\DAV\Server;
use Sabre\DAV\ServerPlugin;

class PropFindPlugin extends ServerPlugin {
	private Server $server;

	public function initialize(Server $server) {
		$this->server = $server;

		$this->server->on('propFind', [$this, 'propFind']);
	}


	public function propFind(PropFind $propFind, INode $node) {
		if (!($node instanceof FacePhoto)) {
			return;
		}

		$propFind->handle('{http://nextcloud.org/ns}file-name', function () use ($node) {
			return $node->getFile()->getName();
		});
	}
}
