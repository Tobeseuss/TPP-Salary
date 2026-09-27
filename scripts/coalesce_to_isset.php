<?php
/**
 * تبدیل خودکار عملگر ?? به isset ? : (سازگاری PHP 5.6 برای هاست‌های رایگان)
 * توکن‌محور و ایمن: عملوند چپ = زنجیره $var / ['key'] / ->prop
 */
$files = array(
    '/home/z/my-project/build/tpp_salary/tpp-salary.php',
);
foreach (glob('/home/z/my-project/build/tpp_salary/includes/*.php') as $f) {
    $files[] = $f;
}

function find_last_coalesce($tokens) {
    for ($i = count($tokens) - 1; $i >= 0; $i--) {
        $t = $tokens[$i];
        if (is_array($t) && $t[0] === T_COALESCE) return $i;
    }
    return -1;
}

function left_operand_extent($tokens, $coalesce_idx) {
    // از سمت چپ عملگر ?: به عقب می‌رویم: زنجیره $var / [ ... ] / ->prop
    $i = $coalesce_idx - 1;
    while ($i >= 0 && is_array($tokens[$i]) && in_array($tokens[$i][0], array(T_WHITESPACE, T_COMMENT, T_DOC_COMMENT), true)) $i--;
    $start = $i + 1; // exclusive
    while ($i >= 0) {
        $t = $tokens[$i];
        if (is_array($t) && $t[0] === T_WHITESPACE) { $i--; continue; }
        if (is_array($t) && $t[0] === T_VARIABLE) { $start = $i; $i--; continue; } // پایان: متغیر
        if (is_string($t) && $t === ']') {
            // به دنبال [ متناظر
            $depth = 0; $j = $i;
            while ($j >= 0) {
                $tj = $tokens[$j];
                if (is_string($tj)) {
                    if ($tj === ']') $depth++;
                    if ($tj === '[') { $depth--; if ($depth === 0) break; }
                }
                $j--;
            }
            if ($j < 0) break;
            $i = $j; // روی [
            $start = $i;
            $i--;
            continue;
        }
        if (is_array($t) && $t[0] === T_STRING) {
            // prop بعد از ->
            $j = $i - 1;
            while ($j >= 0 && is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) $j--;
            if ($j >= 0 && is_string($tokens[$j]) && $tokens[$j] === '::') {
                // static: همان prop باقی می‌ماند (TPP_X::m) — اما چون isset روی static ممکن نیست، توقف
                break;
            }
            if ($j >= 0 && is_string($tokens[$j]) && $tokens[$j] === '-') {
                // -> در توکنایزر PHP: T_OBJECT_OPERATOR تک‌توکن است؛ این شاخه رخ نمی‌دهد
                break;
            }
            if ($j >= 0 && is_array($tokens[$j]) && $tokens[$j][0] === T_OBJECT_OPERATOR) {
                $start = $j;
                $i = $j - 1;
                continue;
            }
            break;
        }
        break;
    }
    return $start;
}

function right_operand_extent($tokens, $coalesce_idx) {
    $n = count($tokens);
    $i = $coalesce_idx + 1;
    $depth = 0;
    $end = $i; // exclusive
    while ($i < $n) {
        $t = $tokens[$i];
        if (is_string($t)) {
            if ($t === '(' || $t === '[' || $t === '{') { $depth++; $i++; continue; }
            if ($t === ')' || $t === ']' || $t === '}') {
                if ($depth === 0) break; // پرانتز بستنِ محیط بیرونی — داخل نیست
                $depth--; $i++; continue;
            }
            if ($depth === 0 && in_array($t, array(',', ';', ':', '?'), true)) break;
            $i++; continue;
        }
        if (in_array($t[0], array(T_WHITESPACE, T_COMMENT, T_DOC_COMMENT), true)) { $i++; continue; }
        if ($depth === 0 && in_array($t[0], array(T_DOUBLE_ARROW, T_CLOSE_TAG, T_INLINE_HTML, T_LOGICAL_AND, T_LOGICAL_OR), true)) break;
        $i++;
    }
    return $i;
}

function tokens_to_code($tokens) {
    $out = '';
    foreach ($tokens as $t) $out .= is_array($t) ? $t[1] : $t;
    return $out;
}

foreach ($files as $file) {
    $src = file_get_contents($file);
    $changed = 0;
    while (true) {
        $tokens = token_get_all($src);
        $idx = find_last_coalesce($tokens);
        if ($idx < 0) break;
        $ls = left_operand_extent($tokens, $idx);
        $re = right_operand_extent($tokens, $idx);
        if ($ls === $idx || $re === $idx + 1) {
            fwrite(STDERR, "WARN: unhandled coalesce in $file near: ".substr($src, 0, 0)."\n");
            break;
        }
        $left  = array_slice($tokens, $ls, $idx - $ls);
        $right = array_slice($tokens, $idx + 1, $re - $idx - 1);
        $replacement = array_merge(
            array('('),
            array('isset'), array('('),
            $left,
            array(')'), array('?'),
            $left,
            array(':'),
            $right,
            array(')')
        );
        $src = tokens_to_code(array_merge(
            array_slice($tokens, 0, $ls),
            $replacement,
            array_slice($tokens, $re)
        ));
        $changed++;
    }
    if ($changed) {
        file_put_contents($file, $src);
        echo "$file: $changed coalesce(s) converted\n";
    } else {
        echo "$file: clean\n";
    }
}
echo "DONE\n";
