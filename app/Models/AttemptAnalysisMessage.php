<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ChatRole;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One turn of the conversation about a sitting's analysis: what the participant
 * asked back, or what the assistant answered.
 *
 * The analysis itself is not stored here — it is the opening turn and lives on
 * `attempt_analyses` — so this table only ever holds what came after it. The
 * model name and token counts are kept only on the assistant's turns, because
 * they describe a generation and the participant's own message was not generated
 * by anything.
 */
#[Fillable(['attempt_analysis_id', 'role', 'content', 'model', 'prompt_tokens', 'completion_tokens'])]
final class AttemptAnalysisMessage extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => ChatRole::class,
            'prompt_tokens' => 'int',
            'completion_tokens' => 'int',
        ];
    }

    /**
     * @return BelongsTo<AttemptAnalysis, $this>
     */
    public function analysis(): BelongsTo
    {
        return $this->belongsTo(AttemptAnalysis::class, 'attempt_analysis_id');
    }

    public function isFromTutor(): bool
    {
        return $this->role->isFromTutor();
    }
}
