<?php
declare(strict_types=1);

namespace App\Models\Access\Operations\Publications;
use App\Models\Access\Operations\Default\ReadOperation;

class ReadPublicationOperation extends ReadOperation implements PublicationAccessOperation {}