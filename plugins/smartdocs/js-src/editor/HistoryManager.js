/**
 * Gerenciador de histórico para Undo/Redo (Ctrl+Z / Ctrl+Y).
 */

export class HistoryManager {
  constructor(initialState = []) {
    this.history = [JSON.stringify(initialState)];
    this.index = 0;
  }

  push(state) {
    // Remove estados futuros se estivermos no meio do histórico
    if (this.index < this.history.length - 1) {
      this.history = this.history.slice(0, this.index + 1);
    }

    const serialized = JSON.stringify(state);
    // Evita duplicatas consecutivas
    if (serialized === this.history[this.history.length - 1]) {
      return;
    }

    this.history.push(serialized);
    this.index++;

    // Limita a 50 estados
    if (this.history.length > 50) {
      this.history.shift();
      this.index--;
    }
  }

  undo() {
    if (this.index > 0) {
      this.index--;
      return JSON.parse(this.history[this.index]);
    }
    return null;
  }

  redo() {
    if (this.index < this.history.length - 1) {
      this.index++;
      return JSON.parse(this.history[this.index]);
    }
    return null;
  }
}
