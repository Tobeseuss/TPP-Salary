<?php
error_reporting( E_ALL );
define( 'ABSPATH', '/tmp/' );
define( 'TPP_SALARY_DIR', '/home/z/my-project/build/tpp_salary/' );
require TPP_SALARY_DIR . 'includes/class-tppsalary-pdf.php';

// Reflection to test private helpers
$ref = new ReflectionClass( 'TppSalary_PDF' );
$ord = $ref->getMethod( 'ord_utf8' ); $ord->setAccessible( true );
$isarb = $ref->getMethod( 'is_arabic' ); $isarb->setAccessible( true );
$joins = $ref->getMethod( 'joins_prev' ); $joins->setAccessible( true );

foreach ( array( 'ح', 'ق', 'و', 'م', 'د', 'ا', 'ل', 'ب' ) as $ch ) {
        $cp = $ord->invoke( null, $ch );
        printf( "%s cp=%s arabic=%s joins_prev=%s\n", $ch, dechex( $cp ),
                $isarb->invoke( null, $ch ) ? 'Y' : 'N',
                $joins->invoke( null, $ch ) ? 'Y' : 'N' );
}
