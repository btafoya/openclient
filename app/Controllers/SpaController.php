<?php

namespace App\Controllers;

class SpaController extends BaseController
{
    /**
     * Serve the Vue.js Single Page Application
     *
     * This controller serves the index.html file for all frontend routes,
     * allowing Vue Router to handle client-side routing.
     */
    public function index()
    {
        // Serve the built Vue app's index.html
        $indexPath = FCPATH . 'dist/index.html';

        // In development, serve the development index
        if (!file_exists($indexPath)) {
            // For development mode, we need to proxy to Vite dev server
            // or serve the root index.html
            $devIndexPath = ROOTPATH . 'index.html';

            if (file_exists($devIndexPath)) {
                return file_get_contents($devIndexPath);
            }

            // If neither exists, return helpful error
            throw new \RuntimeException(
                'Vue app not built. Run "npm run build" or "npm run dev"'
            );
        }

        return file_get_contents($indexPath);
    }
}
