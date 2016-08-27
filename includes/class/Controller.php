<?php
    /**
     * Interface: Controller
     * Defines the Controller interface.
     */
    interface Controller {
		# Route constructor calls this to determine the action.
		public function parse($route);

		# Displays the page.
       	public function display($template);
    }
