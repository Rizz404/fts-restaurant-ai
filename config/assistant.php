<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Assistant
    |--------------------------------------------------------------------------
    |
    | Budgets for one AI turn. Provider connection values live in
    | config/services.php under "local_llm".
    |
    */

    'enabled' => (bool) env('ASSISTANT_ENABLED', false),

    // restaurant-v2 adds the tool rules; restaurant-v1 is used when tools are off.
    'prompt_version' => env('ASSISTANT_TOOLS_ENABLED', true) ? 'restaurant-v2' : 'restaurant-v1',

    // Tool calling (Plan 7). Turn off for providers without function calling;
    // chat then answers from retrieval only and requests use the manual forms.
    'tools' => [
        'enabled' => (bool) env('ASSISTANT_TOOLS_ENABLED', true),
    ],

    'deadline_seconds' => 60,
    'connect_timeout_seconds' => 5,
    // Conversation/user lease; longer than the deadline so a slow turn can finish.
    'lease_seconds' => 75,
    // Browser gives up waiting after this many seconds and polls the turn.
    'client_timeout_seconds' => 70,

    'max_completion_calls' => 7,
    'max_tool_rounds' => 6,
    'max_tool_calls_per_response' => 5,
    'max_total_invocations' => 12,

    'history' => [
        'max_messages' => 20,
        'max_characters' => 12000,
    ],

    'evidence' => [
        'max_menu_items' => 8,
        'max_info_items' => 5,
        'max_characters_per_item' => 2000,
    ],

    // Public guest budgets (Plan 5 enforces them): turns per guest session per
    // day, and a global daily cap. Exceeding either gives the
    // ai_budget_exhausted fallback while browsing and forms keep working.
    'guest' => [
        'daily_turns' => (int) env('ASSISTANT_GUEST_DAILY_TURNS', 40),
    ],

    'daily_turn_cap' => (int) env('ASSISTANT_DAILY_TURN_CAP', 2000),

    'max_tokens' => 2048,
    'temperature' => 0.3,

    // Provider capability: only sent when configured. Thinking models such as
    // Qwen3 on LM Studio need "none" so the answer is not spent on reasoning.
    'reasoning_effort' => env('ASSISTANT_REASONING_EFFORT') ?: null,

    // Longest assistant text accepted from the model.
    'max_answer_characters' => 4000,

];
