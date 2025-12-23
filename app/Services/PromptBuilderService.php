<?php

namespace App\Services;

class PromptBuilderService
{
    /**
     * Build the complete system prompt for AI processing
     *
     * @param array $knowledgeDocuments Array of knowledge document strings
     * @param string $language Language code (en, es, fr, etc.)
     * @return string Complete prompt string
     */
    public function buildPrompt(array $knowledgeDocuments, string $language = 'en'): string
    {
        $systemPrompt = $this->getSystemPrompt($language);
        $knowledgeSection = $this->formatKnowledge($knowledgeDocuments, $language);
        $fallbackInstructions = $this->getFallbackInstructions($language);

        return $systemPrompt . "\n\n" . $knowledgeSection . "\n\n" . $fallbackInstructions;
    }

    /**
     * Get the base system prompt enforcing knowledge-only answers
     */
    private function getSystemPrompt(string $language): string
    {
        $prompts = [
            'en' => "You are a knowledgeable AI assistant for a business support system. " .
                   "You must ONLY answer questions using the knowledge documents provided below. " .
                   "Do not use any external knowledge, make assumptions, or provide information not contained in the documents. " .
                   "Be helpful, accurate, and concise in your responses.",

            'es' => "Eres un asistente de IA conocedor para un sistema de soporte empresarial. " .
                   "Debes RESPONDER SOLO preguntas usando los documentos de conocimiento proporcionados a continuación. " .
                   "No uses conocimiento externo, hagas suposiciones o proporciones información no contenida en los documentos. " .
                   "Sé útil, preciso y conciso en tus respuestas.",

            'fr' => "Vous êtes un assistant IA compétent pour un système de support d'entreprise. " .
                   "Vous devez SEULEMENT répondre aux questions en utilisant les documents de connaissance fournis ci-dessous. " .
                   "N'utilisez pas de connaissances externes, ne faites pas d'hypothèses ou ne fournissez pas d'informations non contenues dans les documents. " .
                   "Soyez utile, précis et concis dans vos réponses.",

            'de' => "Sie sind ein kompetenter KI-Assistent für ein Geschäftssupportsystem. " .
                   "Sie dürfen NUR Fragen beantworten, indem Sie die unten bereitgestellten Wissensdokumente verwenden. " .
                   "Verwenden Sie kein externes Wissen, machen Sie keine Annahmen oder geben Sie Informationen, die nicht in den Dokumenten enthalten sind. " .
                   "Seien Sie hilfreich, genau und präzise in Ihren Antworten.",
        ];

        return $prompts[$language] ?? $prompts['en'];
    }

    /**
     * Format the retrieved knowledge documents for injection
     */
    private function formatKnowledge(array $knowledgeDocuments, string $language): string
    {
        $headers = [
            'en' => 'Knowledge Base Documents:',
            'es' => 'Documentos de Base de Conocimiento:',
            'fr' => 'Documents de Base de Connaissances:',
            'de' => 'Wissensdatenbank-Dokumente:',
        ];

        $header = $headers[$language] ?? $headers['en'];

        if (empty($knowledgeDocuments)) {
            $emptyMessages = [
                'en' => 'No knowledge documents are currently available.',
                'es' => 'No hay documentos de conocimiento disponibles actualmente.',
                'fr' => 'Aucun document de connaissance n\'est actuellement disponible.',
                'de' => 'Derzeit sind keine Wissensdokumente verfügbar.',
            ];
            return $header . "\n" . ($emptyMessages[$language] ?? $emptyMessages['en']);
        }

        $formatted = $header . "\n\n";
        foreach ($knowledgeDocuments as $index => $document) {
            $formatted .= ($index + 1) . ". " . trim($document) . "\n\n";
        }

        return trim($formatted);
    }

    /**
     * Get fallback instructions for when knowledge is insufficient
     */
    private function getFallbackInstructions(string $language): string
    {
        $instructions = [
            'en' => "Fallback Instructions:\n" .
                   "If the user's question cannot be answered using ONLY the knowledge documents above, " .
                   "respond with exactly: 'I'm sorry, but I don't have specific information about that in my knowledge base. " .
                   "Please contact our support team for further assistance.'\n" .
                   "Do not attempt to answer partially or provide general advice.",

            'es' => "Instrucciones de Respaldo:\n" .
                   "Si la pregunta del usuario no puede responderse usando SOLO los documentos de conocimiento anteriores, " .
                   "responde exactamente con: 'Lo siento, pero no tengo información específica sobre eso en mi base de conocimientos. " .
                   "Por favor contacta a nuestro equipo de soporte para más asistencia.'\n" .
                   "No intentes responder parcialmente o proporcionar consejos generales.",

            'fr' => "Instructions de Repli:\n" .
                   "Si la question de l'utilisateur ne peut pas être répondue en utilisant SEULEMENT les documents de connaissance ci-dessus, " .
                   "répondez exactement par : 'Je suis désolé, mais je n'ai pas d'informations spécifiques à ce sujet dans ma base de connaissances. " .
                   "Veuillez contacter notre équipe de support pour une assistance supplémentaire.'\n" .
                   "N'essayez pas de répondre partiellement ou de donner des conseils généraux.",

            'de' => "Fallback-Anweisungen:\n" .
                   "Wenn die Frage des Benutzers NICHT ausschließlich mit den oben genannten Wissensdokumenten beantwortet werden kann, " .
                   "antworten Sie genau mit: 'Es tut mir leid, aber ich habe keine spezifischen Informationen dazu in meiner Wissensdatenbank. " .
                   "Bitte kontaktieren Sie unser Support-Team für weitere Unterstützung.'\n" .
                   "Versuchen Sie nicht, teilweise zu antworten oder allgemeine Ratschläge zu geben.",
        ];

        return $instructions[$language] ?? $instructions['en'];
    }
}
