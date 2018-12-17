<?php

namespace Dekode\InsightlyCli\Models;

class RackspaceLoadBalancer extends Server {

	private $nodes;

	public function add_node( string $node_private_ip ) {
		$this->nodes[] = $node_private_ip;
	}

	public function get_nodes() {
		return $this->nodes;
	}

}