package com.signalforger.phparrayshapes

import com.intellij.lang.annotation.AnnotationHolder
import com.intellij.lang.annotation.Annotator
import com.intellij.lang.annotation.HighlightSeverity
import com.intellij.openapi.editor.DefaultLanguageHighlighterColors
import com.intellij.openapi.editor.colors.TextAttributesKey
import com.intellij.psi.PsiElement
import com.intellij.psi.PsiComment
import java.util.regex.Pattern

/**
 * Annotator that adds syntax highlighting for typed arrays and array shapes
 * in PHP return type declarations, parameter types, and property types.
 */
class ArrayShapesAnnotator : Annotator {

    companion object {
        // Text attribute keys for syntax highlighting
        val ARRAY_TYPE_KEY: TextAttributesKey = TextAttributesKey.createTextAttributesKey(
            "PHP_ARRAY_SHAPE_TYPE",
            DefaultLanguageHighlighterColors.KEYWORD
        )

        val SHAPE_KEY_KEY: TextAttributesKey = TextAttributesKey.createTextAttributesKey(
            "PHP_ARRAY_SHAPE_KEY",
            DefaultLanguageHighlighterColors.INSTANCE_FIELD
        )

        val SHAPE_KEYWORD_KEY: TextAttributesKey = TextAttributesKey.createTextAttributesKey(
            "PHP_SHAPE_KEYWORD",
            DefaultLanguageHighlighterColors.KEYWORD
        )

        // Patterns for matching typed arrays and shapes
        private val TYPED_ARRAY_PATTERN = Pattern.compile(
            "array<[^>]+>"
        )

        private val ARRAY_SHAPE_PATTERN = Pattern.compile(
            "array\\{[^}]+\\}!?"
        )

        private val SHAPE_DECLARATION_PATTERN = Pattern.compile(
            "^\\s*shape\\s+([A-Z][a-zA-Z0-9_]*)\\s*=\\s*array\\{[^}]+\\};?\\s*$",
            Pattern.MULTILINE
        )
    }

    override fun annotate(element: PsiElement, holder: AnnotationHolder) {
        val text = element.text

        // Skip comments and strings
        if (element is PsiComment) return

        // Check for typed arrays: array<Type>
        val typedArrayMatcher = TYPED_ARRAY_PATTERN.matcher(text)
        while (typedArrayMatcher.find()) {
            val start = element.textRange.startOffset + typedArrayMatcher.start()
            val end = element.textRange.startOffset + typedArrayMatcher.end()

            holder.newSilentAnnotation(HighlightSeverity.INFORMATION)
                .range(com.intellij.openapi.util.TextRange(start, end))
                .textAttributes(ARRAY_TYPE_KEY)
                .create()
        }

        // Check for array shapes: array{key: type}
        val arrayShapeMatcher = ARRAY_SHAPE_PATTERN.matcher(text)
        while (arrayShapeMatcher.find()) {
            val start = element.textRange.startOffset + arrayShapeMatcher.start()
            val end = element.textRange.startOffset + arrayShapeMatcher.end()

            holder.newSilentAnnotation(HighlightSeverity.INFORMATION)
                .range(com.intellij.openapi.util.TextRange(start, end))
                .textAttributes(ARRAY_TYPE_KEY)
                .create()
        }

        // Check for shape declarations: shape Name = array{...}
        val shapeDeclarationMatcher = SHAPE_DECLARATION_PATTERN.matcher(text)
        while (shapeDeclarationMatcher.find()) {
            // Highlight 'shape' keyword
            val shapeKeywordStart = element.textRange.startOffset + shapeDeclarationMatcher.start()
            val shapeKeywordEnd = shapeKeywordStart + 5 // length of "shape"

            holder.newSilentAnnotation(HighlightSeverity.INFORMATION)
                .range(com.intellij.openapi.util.TextRange(shapeKeywordStart, shapeKeywordEnd))
                .textAttributes(SHAPE_KEYWORD_KEY)
                .create()
        }
    }
}
