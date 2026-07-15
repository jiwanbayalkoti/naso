<?php

namespace App\Services;

use App\Helpers\VerificationDocumentType;
use App\Models\VerificationDocument;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;

class DocumentUploadService extends BaseService
{
    /**
     * @param  array<string, UploadedFile>  $files
     * @param  array<string, string|null>  $numbers
     * @return Collection<int, VerificationDocument>
     */
    public function storeMany(Model $owner, array $files, array $numbers = [], ?int $userId = null): Collection
    {
        $stored = collect();

        foreach ($files as $type => $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }

            $stored->push($this->storeFor(
                $owner,
                $type,
                $file,
                $this->resolveDocumentNumber($type, $numbers),
                $userId
            ));
        }

        return $stored;
    }

    public function storeFor(
        Model $owner,
        string $type,
        UploadedFile $file,
        ?string $documentNumber = null,
        ?int $userId = null
    ): VerificationDocument {
        $directory = sprintf(
            'verification-documents/%s/%s',
            $owner->getTable(),
            $type
        );

        $path = $file->store($directory, 'public');

        return VerificationDocument::create([
            'documentable_type' => $owner->getMorphClass(),
            'documentable_id' => $owner->id,
            'type' => $type,
            'document_number' => $documentNumber,
            'file_path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'file_size' => $file->getSize(),
            'status' => 'pending',
            'created_by' => $userId,
        ]);
    }

    /**
     * @param  array<string, string|null>  $numbers
     */
    protected function resolveDocumentNumber(string $type, array $numbers): ?string
    {
        return match ($type) {
            VerificationDocumentType::PAN => $numbers['pan_number'] ?? null,
            VerificationDocumentType::NID => $numbers['nid_number'] ?? null,
            VerificationDocumentType::LICENSE => $numbers['license_number'] ?? null,
            default => null,
        };
    }
}
