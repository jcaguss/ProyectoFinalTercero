function calcular() {

    const masa = parseFloat(document.getElementById("dinosaurio").value);
    const mu = parseFloat(document.getElementById("rozamiento").value);
    const anguloGrados = parseFloat(document.getElementById("angulo").value);

    // Validaciones

    if (isNaN(masa) || isNaN(anguloGrados)) {
        document.getElementById("resultado").innerHTML =
            "<p>Por favor, completa todos los campos.</p>";
        return;
    }

    if (anguloGrados <= 0 || anguloGrados > 90) {
        document.getElementById("resultado").innerHTML =
            "<p>'El ángulo debe estar entre 0° y 90°.</p>";
        return;
    }

    if (mu < 0 || mu > 1) {
        document.getElementById("resultado").innerHTML =
            "<p>El coeficiente de rozamiento debe estar entre 0 y 1.</p>";
        return;
    }

    // Cálculos
    const anguloRad = (anguloGrados * Math.PI) / 180;
    const g = 9.8;
    const p = masa * g;
    const aceleracion = g * (Math.sin(anguloRad) - mu * Math.cos(anguloRad));

    let mensajeAceleracion;
if (aceleracion > 0) {
    mensajeAceleracion = ` El dinosaurio acelera hacia abajo: ${aceleracion.toFixed(2)} m/s²`;
} else if (aceleracion === 0) {
    mensajeAceleracion = ` El dinosaurio baja con velocidad constante: ${aceleracion.toFixed(2)} m/s²`;
}

document.getElementById("resultado").innerHTML = 
    `<p><strong>${mensajeAceleracion}</strong></p>
     <p><small>Nota: aunque cambiemos el dinosaurio, en un plano inclinado, todos los objetos caen con la misma aceleración independientemente de su masa. Esto fue descubierto por Galileo.</small></p>
     <p><small>Usando la fórmula: g * (Math.sin - mu * Math.cos)</small></p>`;
}

// **como caso especial* si mu es mayor que 1 en un caso especial como el caucho, el cuerpo sólo quedaría frenado, no podría tener aceleración negativa.