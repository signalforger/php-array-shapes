package com.signalforger.phparrayshapes

import com.intellij.openapi.editor.colors.TextAttributesKey
import com.intellij.openapi.fileTypes.SyntaxHighlighter
import com.intellij.openapi.options.colors.AttributesDescriptor
import com.intellij.openapi.options.colors.ColorDescriptor
import com.intellij.openapi.options.colors.ColorSettingsPage
import javax.swing.Icon

/**
 * Color settings page for array shapes syntax highlighting customization.
 */
class ArrayShapesColorSettingsPage : ColorSettingsPage {

    companion object {
        private val DESCRIPTORS = arrayOf(
            AttributesDescriptor("Array type (array<T>, array{...})", ArrayShapesAnnotator.ARRAY_TYPE_KEY),
            AttributesDescriptor("Shape key name", ArrayShapesAnnotator.SHAPE_KEY_KEY),
            AttributesDescriptor("Shape keyword", ArrayShapesAnnotator.SHAPE_KEYWORD_KEY)
        )
    }

    override fun getIcon(): Icon? = null

    override fun getHighlighter(): SyntaxHighlighter? = null

    override fun getDemoText(): String {
        return """
            <?php

            // Typed arrays
            function getIds(): <array_type>array<int></array_type> {
                return [1, 2, 3];
            }

            function getUsers(): <array_type>array<User></array_type> {
                return [];
            }

            // Array shapes
            function getUser(): <array_type>array{id: int, name: string}</array_type> {
                return ['id' => 1, 'name' => 'Alice'];
            }

            // Closed shapes
            function getStrictUser(): <array_type>array{id: int, name: string}!</array_type> {
                return ['id' => 1, 'name' => 'Alice'];
            }

            // Shape declarations
            <shape_keyword>shape</shape_keyword> User = array{id: int, name: string, email: string};
            <shape_keyword>shape</shape_keyword> Config = array{debug: bool, cache_ttl?: int};
        """.trimIndent()
    }

    override fun getAdditionalHighlightingTagToDescriptorMap(): Map<String, TextAttributesKey> {
        return mapOf(
            "array_type" to ArrayShapesAnnotator.ARRAY_TYPE_KEY,
            "shape_key" to ArrayShapesAnnotator.SHAPE_KEY_KEY,
            "shape_keyword" to ArrayShapesAnnotator.SHAPE_KEYWORD_KEY
        )
    }

    override fun getAttributeDescriptors(): Array<AttributesDescriptor> = DESCRIPTORS

    override fun getColorDescriptors(): Array<ColorDescriptor> = ColorDescriptor.EMPTY_ARRAY

    override fun getDisplayName(): String = "PHP Array Shapes"
}
