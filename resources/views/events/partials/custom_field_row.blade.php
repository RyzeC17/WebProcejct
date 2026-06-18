@php
    $displayOrder = $row['display_order'] ?? (is_numeric($index) ? ((int) $index + 1) : '');
@endphp
<div class="custom-field-row" data-custom-field-row>
    <input type="hidden" name="custom_fields[{{ $index }}][id]" value="{{ $row['id'] ?? '' }}">
    <input type="checkbox" name="custom_fields[{{ $index }}][DELETE]" value="1" class="d-none">
    <div class="custom-field-grid">
        <div class="form-group">
            <label class="form-label" for="custom_fields_{{ $index }}_label">Etichetta</label>
            <input class="form-control" id="custom_fields_{{ $index }}_label" name="custom_fields[{{ $index }}][label]" maxlength="120" value="{{ $row['label'] ?? '' }}">
        </div>
        <div class="form-group">
            <label class="form-label" for="custom_fields_{{ $index }}_field_type">Tipo</label>
            <select class="form-select" id="custom_fields_{{ $index }}_field_type" name="custom_fields[{{ $index }}][field_type]" data-custom-field-type>
                @foreach ($fieldTypes as $value => $label)
                    <option value="{{ $value }}" @selected(($row['field_type'] ?? 'text') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="form-group">
            <label class="form-label" for="custom_fields_{{ $index }}_display_order">Ordine</label>
            <input class="form-control" id="custom_fields_{{ $index }}_display_order" name="custom_fields[{{ $index }}][display_order]" type="number" min="1" value="{{ $displayOrder }}">
        </div>
        <div class="form-group custom-field-toggle">
            <label class="form-label d-block" for="custom_fields_{{ $index }}_is_required">Obbligatorio</label>
            <div class="custom-field-checkbox">
                <input class="form-check-input mt-0" id="custom_fields_{{ $index }}_is_required" name="custom_fields[{{ $index }}][is_required]" type="checkbox" value="1" @checked(!empty($row['is_required']))>
            </div>
        </div>
        <div class="custom-field-actions">
            <button class="btn btn-outline-danger btn-sm w-100" type="button" data-remove-custom-field>Rimuovi</button>
        </div>
        <div class="custom-field-options form-group" data-custom-field-options>
            <label class="form-label" for="custom_fields_{{ $index }}_options_text">Opzioni</label>
            <textarea class="form-control" id="custom_fields_{{ $index }}_options_text" name="custom_fields[{{ $index }}][options_text]" rows="3" placeholder="Una opzione per riga">{{ $row['options_text'] ?? '' }}</textarea>
            <div class="form-text">Compila solo per i campi di selezione singola, una voce per riga.</div>
        </div>
    </div>
</div>
