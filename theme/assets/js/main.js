document.addEventListener("DOMContentLoaded", () => {
  "use strict";

  /* ============================== 
     Constantes y referencias DOM
     ============================== */
  const container =
    document.querySelector(".form-tasacion-container") || document;
  const selects = container.querySelectorAll("select.form-select-size");
  const btnStarter = document.getElementById("btn-starter");
  const ajaxUrl = ajax_object.ajax_url ?? "";
  const nonce = ajax_object.nonce ?? "";

  // Reglas de negocio
  const blockedBrands = ["tesla", "jaguar", "land rover"];
  const YEAR_MAX_AGE = 9; // máximo de antigüedad (años) para tasar
  const VALUATION_MAX_KM = 140000; // máximo de km para tasar

  // Año actual
  const currentYear = new Date().getFullYear();

  // IDs de Gravity Forms
  const GF_IDS = {
    form: "form-tasacion",
    inputData: "input_9_13",
    inputBase: "input_9_14",
    inputFinal: "input_9_29",
    inputEraNuevo: "input_9_21",
    inputExt: "input_9_22",
    inputLibro: "input_9_23",
    inputLlaves: "input_9_24",
    inputInt: "input_9_25",
    gformNextBtn: "gform_next_button_9_28",
  };

  // Mensajes
  const QUOTE_FAIL_MSG = "No hemos podido calcular la tasación de tu vehículo";

  /* ================================== 
     Utilities - Funciones auxiliares
     ================================== */

  /**
   * Detecta si el dispositivo es táctil (móvil/tablet)
   * @returns {boolean}
   */
  const isTouch = () =>
    (window.matchMedia && window.matchMedia("(pointer: coarse)").matches) ||
    "ontouchstart" in window;

  /**
   * Obtiene un elemento del DOM por su ID (null si no existe)
   * @param {string} id
   * @returns {HTMLElement|null}
   */
  const getElement = (id) => document.getElementById(id);

  /**
   * Establece el texto de un elemento del DOM por su ID
   * @param {string} id
   * @param {string} value
   */
  const setText = (id, value) => {
    const el = getElement(id);
    if (el) el.textContent = value ?? "";
  };

  /**
   * Comprueba si una marca está bloqueada
   * @param {string} name
   * @returns {boolean}
   */
  const isBrandBlocked = (name) =>
    blockedBrands.includes(
      String(name || "")
        .trim()
        .toLowerCase()
    );

  /**
   * Comprueba si un año es elegible (no más viejo que YEAR_MAX_AGE)
   * @param {number|string} y
   * @returns {boolean}
   */
  const isYearEligible = (y) =>
    Number.isFinite(Number(y)) &&
    currentYear - Number.parseInt(y, 10) < YEAR_MAX_AGE;

  /**
   * Comprueba si los km exceden el máximo permitido
   * @param {number} km
   * @returns {boolean}
   */
  const isTooManyKm = (km) => Number.isFinite(km) && km > VALUATION_MAX_KM;

  /* ======================= 
     Helpers para <select>
     ======================= */

  /**
   * Cuenta el número de opciones "reales" (sin placeholder) en un <select>
   * @param {HTMLSelectElement} select
   * @returns {number}
   */
  const countRealOptions = (select) => {
    if (!select || !select.options) return 0;
    let start = 0;
    const first = select.options[0];
    if (first && (first.value === "" || first.dataset.placeholder === "1"))
      start = 1; // salta placeholder
    let count = 0;
    for (let i = start; i < select.options.length; i++) {
      const option = select.options[i];
      if (!option.disabled && !option.hidden) count++;
    }
    return count;
  };

  /**
   * Si solo hay una opción "real" (no placeholder), la selecciona automáticamente
   * @param {HTMLSelectElement} select
   * @returns {boolean} si hizo autoselección
   */
  const autoSelectIfSingleOption = (select) => {
    if (!select) return false;
    const optionsArr = Array.from(select.options || []);
    const realOptions = optionsArr.filter((opt) => {
      const isPlaceholder = opt.value === "" || opt.dataset.placeholder === "1";
      return !opt.disabled && !opt.hidden && !isPlaceholder;
    });

    if (realOptions.length === 1) {
      const only = realOptions[0];
      const idx = optionsArr.indexOf(only);
      select.selectedIndex = idx; // selecciona la única opción
      // Forzar recalculo del estado siguiente si existe hook
      if (typeof select._refreshNextState === "function") {
        select._refreshNextState();
      } else {
        select.dispatchEvent(new Event("change", { bubbles: true }));
      }
      return true;
    }
    return false;
  };

  /**
   * Aplica un tamaño adaptativo a un <select> en función de la cantidad de opciones (solo desktop)
   * @param {HTMLSelectElement} select
   * @returns {void}
   */
  const applyAdaptiveSize = (select) => {
    if (!select) return;
    if (isTouch()) return; // solo escritorio
    const count = countRealOptions(select);
    if (count > 0) {
      const size = Math.min(count, 6);
      select.setAttribute("size", String(size));
      select.classList.add("is-listbox");
    } else {
      select.removeAttribute("size");
      select.classList.remove("is-listbox");
    }
  };

  /**
   * Aplica el modo adaptativo (nativo en móvil, listbox en desktop) a todos los selects
   * y añade listeners para cambios de tamaño/orientación
   * @returns {void}
   */
  const applyMode = () => {
    selects.forEach((sel) => {
      if (isTouch()) {
        // móvil: usar el control nativo (sin size)
        if (sel.hasAttribute("size")) {
          sel.dataset.originalSize = sel.getAttribute("size");
          sel.removeAttribute("size");
        }
        sel.classList.remove("is-listbox");
      } else {
        // escritorio: usar listbox con tamaño (restaurando si es posible)
        if (!sel.hasAttribute("size")) {
          const orig = sel.dataset.originalSize || "6";
          sel.setAttribute("size", orig);
        }
        sel.classList.add("is-listbox");
      }
    });
  };

  // Inicializa modo y resizes
  applyMode();
  window.addEventListener("resize", applyMode);
  window.addEventListener("orientationchange", applyMode);

  /* ================================= 
     Stepper y navegación entre pasos
     ================================= */

  /**
   * Activa el paso correspondiente en el stepper
   * @param {number} stepNumber - Número del paso a activar (1..4)
   */
  const setStepperActive = (stepNumber) => {
    const items = document.querySelectorAll("#tasacion-stepper .step-item");
    items.forEach((li) => {
      const n = parseInt(li.getAttribute("data-step"), 10);
      li.classList.toggle("active", n === stepNumber);
      li.classList.toggle("complete", n < stepNumber);
    });
  };

  /**
   * Muestra el paso indicado y oculta los demás
   * @param {number} stepNumber - Número del paso a mostrar (1..4)
   */
  const showStep = (stepNumber) => {
    document
      .querySelectorAll(".form-step")
      .forEach((step) => step.classList.add("d-none"));
    const step = getElement(`step-${stepNumber}`);
    if (step) step.classList.remove("d-none");
    if (btnStarter) btnStarter.classList.toggle("d-none", stepNumber === 1);
  };

  /* ======================= 
     Fetch/AJAX (fetch API)
     ======================= */

  /**
   * Realiza peticiones POST con body urlencoded y nonce WP
   * @param {string} action - Acción a realizar
   * @param {Object} [dataPayload={}] - Datos a enviar en la petición
   * @returns {Promise<any>} - Respuesta de la API
   */
  const fetchData = async (action, dataPayload = {}) => {
    const params = new URLSearchParams();
    params.append("action", action);
    Object.keys(dataPayload || {}).forEach((key) => {
      const val = dataPayload[key];
      // normaliza valores primitivos
      params.append(key, val ?? "");
    });

    // Construir las opciones de la petición
    const options = {
      method: "POST",
      headers: { "X-WP-Nonce": nonce },
      body: params,
    };

    const response = await fetch(ajaxUrl, options);
    return response.json();
  };

  /* =================================== 
     Renderizado de opciones de select
     =================================== */

  /**
   * Renderiza las opciones de un <select>
   * @param {string} selectId - ID del <select> a renderizar
   * @param {Array<{id: string, name: string}>} options - Opciones a agregar [{id, name}]
   * @param {Object} opts - Opciones adicionales
   * @param {string} opts.placeholder - Texto del placeholder (opción vacía)
   * @param {boolean} opts.disableBlocked - Si true, deshabilita las marcas bloqueadas
   * @returns {void}
   */
  const renderSelectOptions = (
    selectId,
    options = [],
    { placeholder = "Selecciona una opción", disableBlocked = false } = {}
  ) => {
    const select = getElement(selectId);
    if (!select) return;

    // Limpia
    select.innerHTML = "";

    // Placeholder (solo en móvil, para mantener comportamiento nativo)
    if (isTouch()) {
      const ph = document.createElement("option");
      ph.value = "";
      ph.textContent = placeholder;
      ph.dataset.placeholder = "1"; // marca de placeholder
      select.appendChild(ph);
    }

    options.forEach((item) => {
      const opt = document.createElement("option");
      opt.value = item.id ?? "";
      opt.textContent = item.name ?? String(item.id ?? "");
      if (disableBlocked && isBrandBlocked(item.name)) {
        opt.disabled = true;
        opt.textContent = `${item.name} (no disponible)`;
      }

      select.appendChild(opt);
    });
  };

  /* =============================================== 
     Wiring de selects con hidden + botón siguiente
     =============================================== */

  /**
   * Vincula un <select> con sus hidden y con el botón "Siguiente"
   * @param {string} selectId
   * @param {string|null} hiddenValueId
   * @param {string|null} hiddenNameId
   * @param {string} nextBtnId
   * @returns
   */
  const wireSelect = (selectId, hiddenValueId, hiddenNameId, nextBtnId) => {
    const select = getElement(selectId);
    const nextBtn = getElement(nextBtnId);
    if (!select || !nextBtn) return;

    const hiddenValue = hiddenValueId ? getElement(hiddenValueId) : null;
    const hiddenName = hiddenNameId ? getElement(hiddenNameId) : null;

    const hasValidSelection = () => {
      const idx = select.selectedIndex;
      if (idx < 0) return false;
      const opt = select.options[idx];
      return !!opt && !opt.disabled && opt.value !== "";
    };

    const refresh = () => {
      const valid = hasValidSelection();
      nextBtn.disabled = !valid;

      if (hiddenValue) hiddenValue.value = valid ? select.value : "";
      if (hiddenName) {
        const idx = select.selectedIndex;
        const opt = idx >= 0 ? select.options[idx] : null;
        hiddenName.value = valid && opt ? opt.textContent : "";
      }
    };

    // hook para forzar el recalculo tras repintar opciones
    select._refreshNextState = refresh;
    select.addEventListener("change", refresh);

    // estado inicial
    refresh();
  };

  /* ======================== 
     Carga genérica a select
     ======================== */

  /**
   * Carga genérica de un endpoint a un select (normaliza array)
   * @param {string} action
   * @param {Object} payload
   * @param {string} selectId
   * @param {string} placeholder
   * @param {Object} opts
   * @returns
   */
  const loadToSelect = async (
    action,
    payload,
    selectId,
    placeholder,
    { disableBlocked = false } = {}
  ) => {
    const select = getElement(selectId);
    if (!select) return;
    renderSelectOptions(selectId, [], { placeholder: "Cargando..." });

    try {
      const data = await fetchData(action, payload);
      let items = [];
      if (data.success && data.data) {
        items = Array.isArray(data.data) ? data.data : [data.data];
      }
      items = items.map((it) => ({
        id: it?.id ?? it?.value ?? it?.code ?? it ?? "",
        name: it?.name ?? it?.label ?? String(it ?? ""),
      }));

      renderSelectOptions(selectId, items, { placeholder, disableBlocked });
      autoSelectIfSingleOption(select);
      applyAdaptiveSize(select);
      if (typeof select._refreshNextState === "function")
        select._refreshNextState();
    } catch (e) {
      renderSelectOptions(selectId, [], { placeholder: "—" });
      alert("Error al obtener los datos. Inténtalo de nuevo.");
      console.error("[loadToSelect] Error:", err);
    }
  };

  // Helpers para limpiar selects dependientes
  const resetSelect = (selectId, placeholder) =>
    renderSelectOptions(selectId, [], { placeholder });

  /* ======================== 
     Flujo de pasos: loaders
     ======================== */

  // Paso 1: Marcas
  const loadMarcas = async () => {
    await loadToSelect(
      "obtener_marcas",
      {},
      "brand-select",
      "Selecciona una marca",
      { disableBlocked: true }
    );
  };

  // Paso 2: Modelos
  const loadModelos = async (makeId) => {
    await loadToSelect(
      "obtener_modelos_por_marca",
      { makeId },
      "model-select",
      "Selecciona un modelo"
    );
  };

  // Paso 3: Versiones
  const loadVersiones = async (makeId, modelId) => {
    await loadToSelect(
      "obtener_versiones_por_modelo",
      { makeId, modelId },
      "version-select",
      "Selecciona una versión"
    );
  };

  // Paso 4: Años
  const loadYears = async (makeId, modelId) => {
    await loadToSelect(
      "obtener_datos_por_marca_modelo",
      { makeId, modelId, dataKey: "years" },
      "year-select",
      "Selecciona el año"
    );
  };

  // Paso 5: Puertas
  const loadDoors = async (makeId, modelId) => {
    await loadToSelect(
      "obtener_datos_por_marca_modelo",
      { makeId, modelId, dataKey: "doors" },
      "doors-select",
      "Selecciona nº de puertas"
    );
  };

  /* ==================================================
     KM (mileage) wiring: habilitar siguiente si válido
     (IIFE convertido en función autoejecutable)
     ================================================== */
  (function wireMileageNext() {
    const mileageInput = getElement("mileage");
    const nextBtn = getElement("next-6");
    const msgKm = getElement("message-too-many-km");
    if (!mileageInput || !nextBtn) return;

    const updateState = () => {
      const raw = String(mileageInput.value || "");
      const number = parseInt(raw.replace(/\D/g, ""), 10);
      const hasNumber = Number.isFinite(number) && number >= 0;
      const tooMany = isTooManyKm(number); // > 140000

      if (msgKm) msgKm.style.display = tooMany ? "block" : "none";
      nextBtn.disabled = !(hasNumber && !tooMany);
    };

    mileageInput.addEventListener("input", updateState);
    mileageInput.addEventListener("change", updateState);
    mileageInput.addEventListener("keydown", (e) => {
      if (e.key === "Enter") {
        e.preventDefault();
        if (!nextBtn.disabled) nextBtn.click();
      }
    });

    // estado inicial
    updateState();
  })();

  /* ================================================
     Paso: mostrar GF y calcular tasación (nextToGF)
     ================================================ */
  (function nextToGF() {
    const nextBtn = getElement("next-6");
    if (!nextBtn) return;

    nextBtn.addEventListener("click", async () => {
      // Normaliza km
      const mileageInput = getElement("mileage");
      const mileage = mileageInput
        ? parseInt((mileageInput.value || "").replace(/\D/g, ""), 10)
        : NaN;
      if (Number.isFinite(mileage)) mileageInput.value = String(mileage);

      // Recoger datos seleccionados
      const makeId = getElement("selectedBrand")?.value ?? "";
      const modelId = getElement("selectedModel")?.value ?? "";
      const versionId = getElement("selectedVersion")?.value ?? "";
      const year = parseInt(getElement("selectedYear")?.value ?? "0", 10);
      const doors = parseInt(getElement("selectedDoors")?.value ?? "0", 10);
      const licensePlate = (getElement("licensePlate")?.value ?? "").trim();

      const formDataNames = {
        make: getElement("selectedBrandName")?.value ?? "",
        model: getElement("selectedModelName")?.value ?? "",
        version: getElement("selectedVersionName")?.value ?? "",
        doors:
          parseInt(getElement("selectedDoorsName")?.value ?? "0", 10) || "",
        year: parseInt(getElement("selectedYearName")?.value ?? "0", 10) || "",
        mileage:
          parseInt(getElement("mileage")?.value.replace(/\D/g, ""), 10) || "",
        licensePlate,
      };

      // Campos ocultos GF
      const gfDataField = getElement(GF_IDS.inputData);
      const gfImporteBase = getElement(GF_IDS.inputBase);
      const gfImporteFinal = getElement(GF_IDS.inputFinal);

      // Formatear resumen para enviar en GF
      if (gfDataField) gfDataField.value = formatFormData(formDataNames);

      // Si es tasable, llamamos a la API para obtener la tasación
      const payload = {
        make: makeId,
        model: modelId,
        version: versionId,
        doors: doors,
        year: year,
        mileage: mileage,
      };
      console.log("[Tasación] Payload para tasación:", payload);

      try {
        const result = await fetchData("calcular_tasacion", payload);
        console.log("[Tasación] Resultado de la tasación:", result);

        // let base = 0;
        let base = null;
        if (result?.success === true && result?.data?.quotation != null) {
          base = Number.parseInt(result.data.quotation, 10) || 0;
          if (gfImporteBase) gfImporteBase.value = String(base);
          if (gfImporteFinal) gfImporteFinal.value = ""; // se calcula al "Enviar" GF (Paso 2)
        } else {
          // API responde pero no puede calcular la tasación: usar mensaje de error
          const messageToShow = QUOTE_FAIL_MSG;
          if (gfImporteBase) gfImporteBase.value = messageToShow;
          if (gfImporteFinal) gfImporteFinal.value = messageToShow;

          console.warn(
            "[Tasación] API no devolvió cotización. Mensaje:",
            messageToShow
          );
        }

        console.log("[Tasación] Tasación base:", base);
        computeFinalValuation();
      } catch (err) {
        console.error("[Tasación] Error al calcular la tasación:", err);
        if (gfImporteBase) gfImporteBase.value = QUOTE_FAIL_MSG;
        if (gfImporteFinal) gfImporteFinal.value = QUOTE_FAIL_MSG;
      }

      showStep(7); // Mostrar GF
      setStepperActive(2); // Marcar stepper en paso 2 (Propietario)

      // Foco al primer campo visible de GF y hooks de recaptcha/gform
      setTimeout(() => {
        const gfWrap = document.querySelector("#step-7 .gform_wrapper");
        const gfForm = gfWrap
          ? gfWrap.querySelector('form[id^="gform_"]')
          : null;
        if (!gfWrap || !gfForm) return;
        if (window.gform?.initializeOnLoaded) window.gform.initializeOnLoaded();
        if (window.grecaptcha && gfWrap.querySelector(".gform_recaptcha")) {
          try {
            window.grecaptcha.reset();
          } catch (_) {}
        }

        // Foco al primer campo visible (no hidden) dentro del formulario de GF
        const first = document.querySelector(
          "#step-7 input:not([type=hidden]), #step-7 select, #step-7 textarea"
        );
        if (first) first.focus();

        // Recalcular la tasación final (por si acaso)
        computeFinalValuation();

        // Al hacer click en "Siguiente" de GF ir a 'Preguntas' (step 3)
        getElement(GF_IDS.gformNextButton)?.addEventListener("click", () => {
          setStepperActive(3);
        });
      }, 0);
    });
  })();

  /* ===============================
     Cálculo final de la tasación
     =============================== */

  /**
   * Calcula la tasación final del vehículo según las reglas de negocio
   * @returns {number} importe final calculado
   */
  const computeFinalValuation = () => {
    // Base de Autobiz
    const base = parseInt(getElement(GF_IDS.inputBase)?.value || "0", 10) || 0;
    const out = getElement(GF_IDS.inputFinal);

    // Comprobar si base es realmente un número (entero)
    const isNumericString = /^\s*-?\d+(\.\d+)?\s*$/.test(String(base));
    const baseNumber = isNumericString ? Number.parseInt(base, 10) : null;

    // Si es numérico, continuar con reglas
    let finalAmount = baseNumber;

    // marca (para regla Volvo: -500€)
    const brand = (getElement("selectedBrandName")?.value || "").toLowerCase();
    if (brand.includes("volvo")) finalAmount -= 500;

    // respuestas usuario
    const eraNuevo = getElement(GF_IDS.inputEraNuevo)?.value || ""; // "si" | "no"
    const libro = getElement(GF_IDS.inputLibro)?.value || ""; // "si" | "no"
    const llaves = String(getElement(GF_IDS.inputLlaves)?.value || "").trim(); // "1" | "2"
    const ext = getElement(GF_IDS.inputExt)?.value || "";
    const int = getElement(GF_IDS.inputInt)?.value || "";

    // penalizaciones
    if (eraNuevo === "no") finalAmount -= 300;
    if (libro === "no") finalAmount -= 300;
    if (llaves === "1") finalAmount -= 300;

    var condPenalty = {
      perfectas_condiciones: 0,
      ligeramente_desgastado: -300,
      desgastado: -600,
    };
    finalAmount += condPenalty[ext] ?? 0;
    finalAmount += condPenalty[int] ?? 0;

    // nunca negativo
    if (finalAmount < 0) finalAmount = 0;

    // setea el oculto "importe final"
    if (out) out.value = String(finalAmount);

    console.log(
      "[Tasación] calc -> base:",
      base,
      "| eraNuevo:",
      eraNuevo,
      "libro:",
      libro,
      "llaves:",
      llaves,
      "ext:",
      ext,
      "int:",
      int,
      "| final:",
      finalAmount
    );
    return finalAmount;
  };

  // Recalcular cuando cambien los campos relevantes de GF
  document.addEventListener("change", (ev) => {
    const ids = new Set([
      GF_IDS.inputEraNuevo,
      GF_IDS.inputLibro,
      GF_IDS.inputLlaves,
      GF_IDS.inputInt,
      GF_IDS.inputExt,
      GF_IDS.inputFinal,
    ]);
    const t = ev.target;
    if (!t?.id) return;
    if (ids.has(t.id)) computeFinalValuation();
  });

  /* ============================================
     Wire básicos de selects + validaciones extra
     ============================================ */
  wireSelect("brand-select", "selectedBrand", "selectedBrandName", "next-1");
  wireSelect("model-select", "selectedModel", "selectedModelName", "next-2");
  wireSelect(
    "version-select",
    "selectedVersion",
    "selectedVersionName",
    "next-3"
  );
  wireSelect("year-select", "selectedYear", "selectedYearName", "next-4");

  // Validación extra al cambiar el año (regla de negocio)
  const yearSelect = getElement("year-select");
  if (yearSelect) {
    yearSelect.addEventListener("change", () => {
      const y = parseInt(yearSelect.value, 10);
      const msg = getElement("message-no-version");
      const nextBtn = getElement("next-4");
      const elegible = Number.isFinite(y) && isYearEligible(y);
      if (msg) msg.style.display = elegible ? "none" : "block";
      if (nextBtn) nextBtn.style.display = elegible ? "block" : "none";
    });
  }

  wireSelect("doors-select", "selectedDoors", "selectedDoorsName", "next-5");

  /* =====================
     Botones Siguiente
     ===================== */

  getElement("next-1")?.addEventListener("click", async () => {
    const makeId = getElement("selectedBrand").value;
    const makeName = getElement("selectedBrandName").value;
    if (!makeId) return;

    setText("selectedBrandNameDisplay", makeName);

    resetSelect("model-select", "Selecciona un modelo");
    resetSelect("version-select", "Selecciona una versión");
    resetSelect("year-select", "Selecciona el año");
    resetSelect("doors-select", "Selecciona nº de puertas");

    await loadModelos(makeId);
    showStep(2);
  });

  getElement("next-2")?.addEventListener("click", async () => {
    const makeId = getElement("selectedBrand").value;
    const modelId = getElement("selectedModel").value;
    const makeName = getElement("selectedBrandName").value;
    const modelName = getElement("selectedModelName").value;
    if (!modelId) return;

    setText("summaryBrand", makeName);
    setText("summaryModel", modelName);

    resetSelect("version-select", "Selecciona una versión");
    resetSelect("year-select", "Selecciona el año");
    resetSelect("doors-select", "Selecciona nº de puertas");

    await loadVersiones(makeId, modelId);
    showStep(3);
  });

  getElement("next-3")?.addEventListener("click", async () => {
    const makeId = getElement("selectedBrand").value;
    const modelId = getElement("selectedModel").value;
    const versionId = getElement("selectedVersion").value;
    if (!versionId) return;

    resetSelect("year-select", "Selecciona el año");
    resetSelect("doors-select", "Selecciona nº de puertas");

    await loadYears(makeId, modelId);
    showStep(4);
  });

  getElement("next-4")?.addEventListener("click", async () => {
    const makeId = getElement("selectedBrand").value;
    const modelId = getElement("selectedModel").value;
    const year = getElement("selectedYear").value;
    if (!year) return;

    // Validar año
    if (!isYearEligible(year)) {
      const msg = getElement("message-no-version");
      if (msg) msg.style.display = "block";
      return;
    }

    resetSelect("doors-select", "Selecciona nº de puertas");
    await loadDoors(makeId, modelId);
    showStep(5);
  });

  getElement("next-5")?.addEventListener("click", () => {
    const doors = getElement("selectedDoors").value;
    if (!doors) return;
    showStep(6);
  });

  /* =================
     Botones "Atrás"
     ================= */
  [
    { btnId: "prev-step-2", step: 1 },
    { btnId: "prev-step-3", step: 2 },
    { btnId: "prev-step-4", step: 3 },
    { btnId: "prev-step-5", step: 4 },
    { btnId: "prev-step-6", step: 5 },
    { btnId: "prev-step-7", step: 6 }, // GF más adelante
  ].forEach(({ btnId, step }) => {
    const btn = getElement(btnId);
    if (btn) btn.addEventListener("click", () => showStep(step));
  });

  /* =================
     Reinicio
     ================= */
  if (btnStarter) {
    btnStarter.addEventListener("click", () => {
      if (
        confirm(
          "¿Deseas reiniciar el proceso de tasación? Se perderán los datos seleccionados."
        )
      ) {
        window.location.reload();
      }
    });
  }

  /* ==================
     Inicio del flujo
     ================== */
  showStep(1);
  loadMarcas();

  /**
   * Convierte los datos del formulario en un string formateado.
   * @param {Object} data - Objeto con los datos del formulario.
   * @returns {string} Texto formateado con saltos de línea.
   */
  const formatFormData = (data) => {
    return `${data.make}
${data.model}
Version: ${data.version}
Puertas: ${data.doors}
Año: ${data.year}
Kilómetros: ${data.mileage}
Matrícula: ${data.licensePlate || "N/A"}`;
  };

  /* ========================================
     Envío final del formulario de Tasación
     ======================================== */
  document.getElementById(GF_IDS.formTasacion).addEventListener(
    "submit",
    (e) => {
      e.preventDefault();
      const prevStepBtn = getElement("prev-step-7");
      if (prevStepBtn) prevStepBtn.disabled = true; // evitar volver atrás
      computeFinalValuation();
      // No hacer nada más, Gravity Forms gestiona el envío
    },
    true
  );
});
