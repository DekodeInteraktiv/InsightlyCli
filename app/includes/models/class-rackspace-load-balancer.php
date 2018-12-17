<?php

namespace Dekode\InsightlyCli\Models;

class RackspaceLoadBalancer extends Server {

	private $nodes;

	/**
	 * Adds the IP address of a node to the list of nodes in this load balancer.
	 *
	 * @param string $node_private_ip The IP address of the node you want to add.
	 */
	public function add_node( string $node_private_ip ) {
		$this->nodes[] = $node_private_ip;
	}

	/**
	 * @return mixed
	 */
	public function get_nodes() {
		return $this->nodes;
	}

}