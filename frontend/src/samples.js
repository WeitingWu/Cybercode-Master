export const samples = {
    JavaScript: {
        error: "Uncaught TypeError: Cannot read properties of undefined (reading 'name')",
        code: `const users = [{ name: 'Neo' }, { name: 'Trinity' }]

function getName(i) {
  return users[i].name
}

console.log(getName(2))`,
    },
    Python: {
        error: 'ZeroDivisionError: division by zero',
        code: `def avg(nums):
    total = 0
    for n in nums:
        total += n
    return total / len(nums)

print(avg([]))`,
    },
    PHP: {
        error: 'Warning: Undefined array key 5',
        code: `<?php
$items = ['a', 'b'];
echo strtoupper($items[5]);`,
    },
};
