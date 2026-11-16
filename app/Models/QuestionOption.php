<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\QuestionOptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['question_id', 'label', 'body', 'is_correct', 'score'])]
class QuestionOption extends Model
{
    /**
     * The five option labels an SKD item offers. This is the one home for the
     * A–E set: the question controller, the store/update request and the admin
     * form all read it from here.
     *
     * @var list<string>
     */
    public const LETTERS = ['A', 'B', 'C', 'D', 'E'];

    /** @use HasFactory<QuestionOptionFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_correct' => 'bool',
            'score' => 'int',
        ];
    }

    /**
     * @return BelongsTo<Question, $this>
     */
    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }
}
