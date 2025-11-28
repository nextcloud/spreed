// https://github.com/jitsi/jitsi-meet/blob/270cdd017ddab7f72896c4194a474ddc7e0d4bf4/react/features/base/util/math.ts#L30
/**
 * Compute the greatest common divisor using Euclid's algorithm.
 *
 * @param {number} num1 - First number.
 * @param {number} num2 - Second number.
 * @returns {number}
 */
export function greatestCommonDivisor(num1, num2) {
    let number1 = num1;
    let number2 = num2;
    while (number1 !== number2) {
        if (number1 > number2) {
            number1 = number1 - number2;
        }
        else {
            number2 = number2 - number1;
        }
    }
    return number2;
}
/**
 * Calculate least common multiple using gcd.
 *
 * @param {number} num1 - First number.
 * @param {number} num2 - Second number.
 * @returns {number}
 */
export function leastCommonMultiple(num1, num2) {
    const number1 = num1;
    const number2 = num2;
    const gcd = greatestCommonDivisor(number1, number2);
    return (number1 * number2) / gcd;
}
