<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The written analysis of one finished sitting, as the gateway returned it.
 *
 * One row per attempt, enforced by a unique index rather than by convention:
 * every generation costs money, so a second one for the same sitting is a bug,
 * not a duplicate to be tolerated. Nothing here is ever edited by hand — it is
 * what the model said at the moment it said it, kept so the result page can show
 * it again without paying for it twice.
 */
#[Fillable(['attempt_id', 'content', 'model', 'prompt_tokens', 'completion_tokens'])]
final class AttemptAnalysis extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'prompt_tokens' => 'int',
            'completion_tokens' => 'int',
        ];
    }

    /**
     * @return BelongsTo<ExamAttempt, $this>
     */
    public function attempt(): BelongsTo
    {
        return $this->belongsTo(ExamAttempt::class, 'attempt_id');
    }

    /**
     * What the participant asked after reading it, and what was answered.
     *
     * The analysis itself is the opening turn of that conversation but not a row
     * here — see the migration — so this returns only the turns that came after
     * it, in order.
     *
     * @return HasMany<AttemptAnalysisMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(AttemptAnalysisMessage::class, 'attempt_analysis_id')->orderBy('id');
    }
}
