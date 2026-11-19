<?php

namespace App\Filament\Resources\AuditLogs;

use App\Filament\Concerns\RequiresPermission;
use App\Models\AuditLog;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class AuditLogResource extends Resource
{
    use RequiresPermission;

    protected static ?string $model = AuditLog::class;

    protected static ?string $permission = null;

    protected static ?string $viewPermission = 'audit.view';

    protected static ?string $navigationLabel = 'Activity history';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static string|UnitEnum|null $navigationGroup = 'Administration';

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('created_at')->dateTime('j M Y H:i:s', config('sudar.business.timezone')),
            TextEntry::make('action'),
            TextEntry::make('actor')->state(fn (AuditLog $r) => $r->actor?->name ?? 'System'),
            TextEntry::make('subject')->state(fn (AuditLog $r) => $r->subject_type ? $r->subject_type.' #'.$r->subject_id : '—'),
            TextEntry::make('reason')->placeholder('—'),
            TextEntry::make('ip_address')->placeholder('—'),
            KeyValueEntry::make('changes')->state(fn (AuditLog $r) => collect($r->changes ?? [])->map(fn ($v) => is_scalar($v) || $v === null ? (string) $v : json_encode($v))->all())->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->dateTime('j M Y H:i', config('sudar.business.timezone'))->sortable(),
                TextColumn::make('action')->badge()->searchable(),
                TextColumn::make('actor_label')->label('By')->state(fn (AuditLog $r) => $r->actor?->name ?? 'System'),
                TextColumn::make('subject_label')->label('Subject')->state(fn (AuditLog $r) => $r->subject_type ? $r->subject_type.' #'.$r->subject_id : '—'),
                TextColumn::make('reason')->limit(40)->placeholder('—'),
            ])
            ->filters([SelectFilter::make('action')->options(fn () => AuditLog::query()->distinct()->orderBy('action')->pluck('action', 'action'))])
            ->recordActions([ViewAction::make()])
            ->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListAuditLogs::route('/')];
    }
}
