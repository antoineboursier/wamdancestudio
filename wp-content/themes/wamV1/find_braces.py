def find_unbalanced_braces(filename):
    depth = 0
    with open(filename, 'r') as f:
        for idx, line in enumerate(f, 1):
            if '{' in line or '}' in line:
                old_depth = depth
                for char in line:
                    if char == '{':
                        depth += 1
                    elif char == '}':
                        depth -= 1
                print(f"Line {idx}: depth {old_depth} -> {depth} | {line.strip()}")
        print(f"Final depth: {depth}")

find_unbalanced_braces('assets/css/components.css')
