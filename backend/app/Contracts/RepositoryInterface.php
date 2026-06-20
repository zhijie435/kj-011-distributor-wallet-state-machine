<?php

namespace App\Contracts;

interface RepositoryInterface
{
    public function find(int $id);

    public function create(array $data);

    public function update(int $id, array $data);

    public function delete(int $id): bool;

    public function paginate(int $perPage = 15, array $filters = []);
}
