<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** New order: 1 the event flyer, 2 the original hero (Tinubu), 3 Seyi Tinubu, then the rest as they were. */
    public function up(): void
    {
        $first = [
            'images/slides/slide-1.webp',
            'images/hero-portrait.webp',
            'images/slides/seyi-tinubu.webp',
        ];

        $ordered = DB::table('hero_slides')->whereIn('image_path', $first)->get(['id', 'image_path'])
            ->sortBy(fn ($slide) => array_search($slide->image_path, $first, true))
            ->pluck('id')
            ->merge(DB::table('hero_slides')->whereNotIn('image_path', $first)->orWhereNull('image_path')->orderBy('position')->orderBy('id')->pluck('id'));

        foreach ($ordered->values() as $index => $id) {
            DB::table('hero_slides')->where('id', $id)->update(['position' => $index + 1]);
        }
    }

    public function down(): void
    {
        // Order is content; nothing to undo.
    }
};
