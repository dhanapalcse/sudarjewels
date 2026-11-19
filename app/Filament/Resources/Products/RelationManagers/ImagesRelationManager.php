<?php

namespace App\Filament\Resources\Products\RelationManagers;

use App\Domain\Catalogue\Images\GoogleDriveClient;
use App\Domain\Catalogue\Images\ProductImageService;
use App\Enums\ImageStatus;
use App\Filament\Support\Guard;
use App\Models\ProductImage;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;

class ImagesRelationManager extends RelationManager
{
    protected static string $relationship = 'images';

    public function form(Schema $schema): Schema
    {
        return $schema->components([TextInput::make('alt')->label('Alt text')->maxLength(255)->required()]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->poll('5s')
            ->reorderable('sort_order')
            ->columns([
                ImageColumn::make('preview')->state(fn (ProductImage $r) => $r->url(320))->square()->imageSize(64),
                TextColumn::make('source')->badge(),
                TextColumn::make('status')->badge()->description(fn (ProductImage $r) => $r->status === ImageStatus::Failed ? $r->error : ($r->isPublished() && $r->status !== ImageStatus::Ready ? 'Previous image still live' : null)),
                TextColumn::make('drive_file_name')->label('Drive file')->placeholder('—'),
                TextColumn::make('dimensions')->state(fn (ProductImage $r) => $r->width ? "{$r->width}×{$r->height}" : null)->placeholder('—'),
                IconColumn::make('is_main')->boolean()->label('Main'),
                TextColumn::make('alt')->limit(30),
            ])
            ->headerActions([
                Action::make('upload')->label('Upload images')->icon('heroicon-o-arrow-up-tray')
                    ->schema([
                        FileUpload::make('files')->multiple()->image()->disk('local')->directory('image-staging')->visibility('private')
                            ->acceptedFileTypes(config('sudar.images.allowed_mimes'))->maxSize((int) (config('sudar.images.max_bytes') / 1024))->maxFiles(10)->required()
                            ->helperText('JPEG, PNG or WebP, at least 600×600 px. Optimised copies are created in the background.'),
                    ])
                    ->action(function (array $data) {
                        foreach ((array) $data['files'] as $path) {
                            app(ProductImageService::class)->queueUpload($this->getOwnerRecord(), $path);
                        }
                        Notification::make()->title('Images queued for processing')->success()->send();
                    }),
                Action::make('drive')->label('Import from Google Drive')->icon('heroicon-o-cloud-arrow-down')
                    ->schema([
                        Textarea::make('links')->label('Drive file links or IDs (one per line)')->rows(4)->required()
                            ->helperText(app(GoogleDriveClient::class)->isConfigured() ? 'Files must be shared with the configured service account.' : 'Google Drive import is not configured on the server yet (see README). Imports will fail until it is.'),
                    ])
                    ->action(function (array $data) {
                        $count = 0;
                        foreach (preg_split('/\R/', trim($data['links'])) as $line) {
                            if (trim($line) === '') {
                                continue;
                            }
                            Guard::run(fn () => app(ProductImageService::class)->queueDriveImport($this->getOwnerRecord(), $line), null, false) && $count++;
                        }
                        if ($count) {
                            Notification::make()->title("{$count} import(s) queued")->success()->send();
                        }
                    }),
            ])
            ->recordActions([
                Action::make('main')->label('Set as main')->icon('heroicon-o-star')->visible(fn (ProductImage $r) => ! $r->is_main && $r->isPublished())
                    ->action(fn (ProductImage $r) => app(ProductImageService::class)->setMain($r)),
                Action::make('reimport')->label('Re-import')->icon('heroicon-o-arrow-path')->visible(fn (ProductImage $r) => $r->source === 'google_drive')
                    ->requiresConfirmation()->modalDescription('The current image stays live until the new import succeeds.')
                    ->action(fn (ProductImage $r) => Guard::run(fn () => app(ProductImageService::class)->reimport($r), 'Re-import queued')),
                EditAction::make()->label('Alt text'),
                DeleteAction::make()->after(function (ProductImage $r) {
                    $paths = array_merge(array_values($r->renditions['webp'] ?? []), isset($r->renditions['jpeg']) ? [$r->renditions['jpeg']] : []);
                    Storage::disk(config('sudar.images.disk'))->delete($paths);
                }),
            ]);
    }
}
