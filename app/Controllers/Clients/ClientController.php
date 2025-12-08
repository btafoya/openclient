<?php

namespace App\Controllers\Clients;

use App\Controllers\BaseController;
use App\Models\ClientModel;

class ClientController extends BaseController
{
    public function index()
    {
        $model = new ClientModel();
        $clients = $model->orderBy('created_at', 'DESC')->findAll();

        return view('clients/index', [
            'title'   => 'Clients',
            'clients' => $clients,
        ]);
    }

    public function create()
    {
        return view('clients/create', ['title' => 'New Client']);
    }

    public function store()
    {
        $data = $this->request->getPost(['name', 'email', 'phone', 'website']);

        $model = new ClientModel();
        $model->insert($data);

        return redirect()->to('/clients');
    }

    public function edit($id)
    {
        $model = new ClientModel();
        $client = $model->find($id);

        if (!$client) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound('Client not found');
        }

        return view('clients/edit', [
            'title'  => 'Edit Client',
            'client' => $client,
        ]);
    }

    public function update($id)
    {
        $data = $this->request->getPost(['name', 'email', 'phone', 'website']);

        $model = new ClientModel();
        $model->update($id, $data);

        return redirect()->to('/clients');
    }
}
