function calcular() {
    
  const masa = parseFloat(document.getElementById("dinosaurio").value);
    const anguloGrados = parseFloat(document.getElementById("angulo").value);

    // Validaciones
    if (isNaN(masa) || isNaN(anguloGrados)) {
        document.getElementById("resultado").innerHTML = 
            "<p>Por favor, completa todos los campos.</p>";
        return;
    }

    if (anguloGrados <= 0 || anguloGrados >= 180) {
        document.getElementById("resultado").innerHTML = 
            "<p>'El ángulo debe estar entre 0° y 180°.</p>";
        return;
    }

     if (anguloGrados <= 0 || anguloGrados >= 46) {
     document.getElementById("resultado").innerHTML = 
         "<p> Para que μ esté entre 0 y 1, el ángulo debe estar entre 0° y 45°.</p>";
     return;
  }

    const anguloRad = (anguloGrados * Math.PI) / 180;

    const mu = Math.tan(anguloRad);

    document.getElementById("resultado").innerHTML = 
        `<p><strong>El coeficiente de rozamiento cinético (μ cinético) es: ${mu.toFixed(3)}</strong></p>
         <p><small>Nota: este valor se calcula asumiendo que el dinosaurio desciende con velocidad constante (aceleración = 0).</small></p>
         <p><small>Usando la fórmula tangente multiplicado el valor del ángulo</small></p>`;
}