<?php

namespace App\Services;

class PromptBuilderService
{
    /**
     * Build the complete prompt for AI chat replies
     *
     * @param string $userMessage The user's message
     * @param array $knowledgeContext Array of retrieved knowledge snippets
     * @return string Complete prompt string
     */
    public function buildChatPrompt(string $userMessage, array $knowledgeContext): string
    {
        $systemPrompt = $this->getSystemPrompt();
        $contextSection = $this->formatKnowledgeContext($knowledgeContext);
        $userSection = $this->formatUserMessage($userMessage);

        return $systemPrompt . "\n\n" . $contextSection . "\n\n" . $userSection;
    }

    /**
     * Get the system prompt for customer support agent behavior
     */
    private function getSystemPrompt(): string
    {
        return "You are a customer support agent. Answer ONLY using the provided knowledge context. If the answer is not in the context, say exactly: \"I'll connect you with a human agent.\" Be helpful, accurate, and concise.";
    }

    /**
     * Format the retrieved knowledge context for injection
     */
    private function formatKnowledgeContext(array $knowledgeContext): string
    {
        if (empty($knowledgeContext)) {
            return "Knowledge Context: No relevant information available.";
        }

        $formatted = "Knowledge Context:\n";
        foreach ($knowledgeContext as $index => $snippet) {
            $formatted .= ($index + 1) . ". " . trim($snippet) . "\n";
        }

        return trim($formatted);
    }

    /**
     * Format the user message for the prompt
     */
    private function formatUserMessage(string $userMessage): string
    {
        return "User: " . trim($userMessage);
    }

    /**
     * Get a token-efficient version of the prompt (shorter system prompt)
     */
    public function buildCompactPrompt(string $userMessage, array $knowledgeContext): string
    {
        $systemPrompt = "Act as customer support agent. Answer ONLY using provided knowledge. If unknown, say: \"I'll connect you with a human agent.\"";

        if (empty($knowledgeContext)) {
            $context = "Knowledge: None available.";
        } else {
            $context = "Knowledge: " . implode(" | ", array_map('trim', $knowledgeContext));
        }

        return $systemPrompt . "\n\n" . $context . "\n\nUser: " . trim($userMessage);
    }
}
