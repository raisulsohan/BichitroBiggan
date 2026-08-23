with open("d:/GitHub/BichitroBiggan/Website/bichitro-biggan/functions.php", "r", encoding="utf-8") as f:
    f_content = f.read()
f_content = f_content.replace("define( 'BB_VERSION', '3.6.0' );", "define( 'BB_VERSION', '3.6.1' );")
with open("d:/GitHub/BichitroBiggan/Website/bichitro-biggan/functions.php", "w", encoding="utf-8") as f:
    f.write(f_content)

with open("d:/GitHub/BichitroBiggan/Website/bichitro-biggan/style.css", "r", encoding="utf-8") as f:
    s_content = f.read()
s_content = s_content.replace("Version: 3.6.0", "Version: 3.6.1")
with open("d:/GitHub/BichitroBiggan/Website/bichitro-biggan/style.css", "w", encoding="utf-8") as f:
    f.write(s_content)
print("Versions bumped!")
