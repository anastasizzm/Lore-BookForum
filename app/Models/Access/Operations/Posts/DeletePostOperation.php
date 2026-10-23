<?php
declare(strict_types=1);

namespace App\Models\Access\Operations\Posts;
use App\Models\Access\Operations\Default\DeleteOperation;

class DeletePostOperation extends DeleteOperation implements PostAccessOperation {}