package com.signalforger.phparrayshapes

import com.intellij.codeInspection.LocalInspectionTool
import com.intellij.codeInspection.ProblemsHolder
import com.intellij.psi.PsiElementVisitor
import com.jetbrains.php.lang.psi.visitors.PhpElementVisitor

/**
 * Inspection that recognizes array shapes syntax and prevents false positive errors.
 * This is a no-op inspection that exists to register the syntax as valid.
 */
class ArrayShapesSyntaxInspection : LocalInspectionTool() {

    override fun buildVisitor(holder: ProblemsHolder, isOnTheFly: Boolean): PsiElementVisitor {
        return object : PhpElementVisitor() {
            // This visitor intentionally does nothing.
            // Its purpose is to register that we handle this syntax,
            // which helps suppress false positive errors from other inspections.
        }
    }

    override fun getDisplayName(): String {
        return "Array shapes syntax support"
    }

    override fun getShortName(): String {
        return "ArrayShapesSyntaxInspection"
    }

    override fun getGroupDisplayName(): String {
        return "PHP"
    }

    override fun isEnabledByDefault(): Boolean {
        return true
    }
}
