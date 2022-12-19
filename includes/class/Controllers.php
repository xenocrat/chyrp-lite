<?php
    /**
     * Class: Controllers
     * Acts as the backbone for all controllers.
     */
    class Controllers {
        # String: $base
        # The base path for this controller.
        public $base = "";

        # Boolean: $clean
        # Does this controller support clean URLs?
        public $clean_urls = true;

        # Array: $urls
        # An array of clean URL => dirty URL translations.
        public $urls = array();

        # Boolean: $feed
        # Serve a syndication feed? <Route> determines this if set to @null@.
        public $feed = null;

        # Boolean: $displayed
        # Has anything been displayed?
        public $displayed = false;

        # Array: $context
        # The context supplied to Twig when displaying pages.
        public $context = array();

        # Integer: $post_limit
        # Item limit for pagination.
        public $post_limit = 10;
    }
