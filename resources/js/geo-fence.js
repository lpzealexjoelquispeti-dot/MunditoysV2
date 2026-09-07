export function dentroDelLimite(geometry, latitud, longitud) {
    if (!Number.isFinite(latitud) || !Number.isFinite(longitud)) return false;
    const poligonos = geometry.type === 'Polygon' ? [geometry.coordinates] : geometry.coordinates;
    return poligonos.some(anillos =>
        enAnillo(longitud, latitud, anillos[0]) &&
        !anillos.slice(1).some(hueco => enAnillo(longitud, latitud, hueco))
    );
}

function enAnillo(x, y, anillo) {
    let dentro = false;
    for (let i = 0, j = anillo.length - 1; i < anillo.length; j = i++) {
        const [ax, ay] = anillo[i];
        const [bx, by] = anillo[j];
        const cruz = (x - ax) * (by - ay) - (y - ay) * (bx - ax);
        if (Math.abs(cruz) <= 1e-12 && x >= Math.min(ax, bx) && x <= Math.max(ax, bx) &&
            y >= Math.min(ay, by) && y <= Math.max(ay, by)) return true;
        if ((ay > y) !== (by > y) && x < (bx - ax) * (y - ay) / (by - ay) + ax) dentro = !dentro;
    }
    return dentro;
}
