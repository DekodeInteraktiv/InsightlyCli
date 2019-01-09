<?php

namespace Dekode\InsightlyCli\Models;

class RackspaceLoadBalancer extends Server {

	private $nodes;

	/**
	 * Returns the name of this server when saving it in Insightly.
	 *
	 * @return string
	 */
	public function get_insightly_name() {
		return $this->get_name();
	}

	public function get_provider_name() {
		return 'Rackspace LB';
	}


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