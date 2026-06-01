import os
import re

def process_file(filepath):
    with open(filepath, 'r', encoding='utf-8') as f:
        content = f.read()

    # 1. Update PSR-7 Request/Response Interfaces
    content = content.replace("use Slim\\Http\\Request;", "use Psr\\Http\\Message\\ServerRequestInterface as Request;")
    content = content.replace("use Slim\\Http\\Response;", "use Psr\\Http\\Message\\ResponseInterface as Response;")
    
    # 2. Container DI array access -> set()
    # Replace $container['key'] = function ($container) { with $container->set('key', function($container) {
    content = re.sub(r"\$container\['([^']+)'\]\s*=\s*(function\s*\()", r"$container->set('\1', \2", content)
    # Replace $container[Class::class] = function...
    content = re.sub(r"\$container\[([^\]]+::class)\]\s*=\s*(function\s*\()", r"$container->set(\1, \2", content)
    
    # 3. Fix returning closures inside set() to just be the closure
    # (Actually php-di uses ->set(key, function...) so the above regex handles the start. We just need a closing parenthesis if we changed it... wait, regex replacement for `set` requires closing `);` at the end of the closure block. This is hard to do with simple regex. Let's just fix the container files manually since there are only 5).

    with open(filepath, 'w', encoding='utf-8') as f:
        f.write(content)

src_dir = r"C:\Users\Khalid\Desktop\bounty\DocsApp\src"
for root, dirs, files in os.walk(src_dir):
    for file in files:
        if file.endswith('.php'):
            process_file(os.path.join(root, file))

print("Applied Regex replacements!")
