# Documento de Investigación del Negocio: Sistema de Gestión de Barbería

**Proyecto:** Sistema Web de Gestión de Clientes, Barberos, Servicios, Citas, Pagos, Comisiones y Horarios  
**Negocio Objetivo:** Barbería "Barber Shop Studio"  
**Fecha:** Noviembre de 2026  

---

## 1. Identificación del Negocio Real

* **Nombre del negocio:** Barbería "Barber Shop Studio"
* **Ubicación:** Bogotá, Colombia.
* **Sector / Tema:** Sector de cuidado personal y belleza masculina (servicios de corte de cabello, arreglo y perfilado de barba, tratamientos capilares, venta de productos estéticos).

---

## 2. Definición de Usuarios del Sistema

* **Administrador / Dueño:** Supervisa los ingresos y egresos de la barbería, define las tarifas y catálogo de servicios, evalúa el desempeño del personal, revisa el reporte de horarios con mayor demanda y calcula la liquidación periódica de comisiones por barbero.
* **Barbero (Quien atiende):** Consulta su agenda diaria de turnos, registra la finalización de cada servicio prestado, notifica disponibilidad en tiempo real y hace seguimiento a sus comisiones ganadas.
* **Cliente (Quien consulta / compra):** Agenda turnos con su barbero preferido, consulta servicios, precios y horarios disponibles, y realiza pagos o confirma reservas.

---

## 3. Proceso Principal Actual (Modo As-Is / Manual)

El flujo de trabajo operativo se realiza de manera manual combinando cuadernos físicos de papel y chats de WhatsApp:

1. **Agendamiento:** El cliente solicita una cita mediante mensaje de WhatsApp o llamada telefónica.
2. **Registro de Turno:** El barbero o el administrador revisa manualmente un cuaderno de citas en la recepción y anota a lápiz el nombre del cliente, la hora y el servicio requerido.
3. **Atención:** Al llegar el cliente, se confirma la cita en la libreta. Si hay retrasos o cruces de horario, el cliente debe esperar en el establecimiento.
4. **Cobro y Registro de Pago:** Al finalizar el servicio, el cliente paga en efectivo o mediante transferencia electrónica (Nequi / Daviplata). El cobro se registra con una marca manual en el cuaderno anotando el valor recibido.
5. **Cierre e Inventario de Comisiones:** Al final de la semana, el administrador revisa renglón por renglón la libreta, suma manualmente el total de servicios de cada barbero y calcula de forma artesanal el porcentaje correspondiente a comisiones y los días de mayor afluencia.

---

## 4. Guía de Entrevista (Preguntas Abiertas al Administrador y Barberos)

**Entrevistado:** Administrador General y Barbero Principal  

1. ¿Cómo se registra y gestiona actualmente la información de los clientes nuevos y recurrentes?
2. ¿De qué manera organizan la agenda diaria de turnos para cada barbero del establecimiento?
3. ¿Qué ocurre cuando dos clientes solicitan la misma hora o cuando un barbero se demora más del tiempo proyectado?
4. ¿Cuánto tiempo le toma al equipo responder a las solicitudes de cita por WhatsApp mientras atiende a clientes en silla?
5. ¿Cuáles son los errores o malentendidos más comunes que ocurren en el registro diario de citas y pagos?
6. ¿Cómo es el proceso manual para calcular la comisión de cada barbero al final de la semana o mes?
7. ¿De qué forma identifican cuáles son los días y franjas horarias con mayor afluencia o demanda de clientes?
8. ¿Qué complicaciones existen cuando un cliente no asiste a su cita ("no-show") o cancela a último minuto?
9. ¿Cómo gestionan las diferentes modalidades de pago (efectivo vs. transferencias electrónicas)?
10. ¿Qué tipo de reportes o métricas le gustaría tener disponibles de manera automática para tomar mejores decisiones en el negocio?

---

## 5. Formato de Observación de Campo

| Campo | Registro de Observación |
| :--- | :--- |
| **Fecha y Hora** | 05 de Noviembre de 2026, 15:00 - 18:00 hs |
| **Lugar** | Barbería "Barber Shop Studio", Sede Teusaquillo |
| **Actividad Observada** | Agendamiento de citas por teléfono/WhatsApp, recepción de clientes, prestación de servicios en silla y cobro en recepción. |
| **Problema Detectado** | 1. El barbero principal interrumpió el corte en 4 ocasiones para contestar WhatsApp y revisar la libreta de papel.<br>2. Se presentó un cruce de horarios a las 16:30 hs por anotar un turno en la hoja equivocada.<br>3. El cálculo semanal de comisiones requirió más de 2 horas de revisión manual de la libreta. |
| **Oportunidad** | Implementar un sistema web dinámico para reservas automáticas de citas, control de agenda en vivo por barbero, cálculo de comisiones en tiempo real y gráfico de métricas de alta demanda. |

---

## 6. Encuesta de Necesidades y Satisfacción (Para Clientes)

1. **¿Qué medio utilizas habitualmente para reservar tu turno en la barbería?**
   - [ ] Mensaje de WhatsApp
   - [ ] Llamada telefónica
   - [ ] Asisto directamente al local a esperar turno
2. **¿Cuánto tiempo tardan habitualmente en responderte o confirmarte la disponibilidad de una cita?**
   - [ ] Menos de 5 minutos
   - [ ] Entre 15 minutos y 1 hora
   - [ ] Más de 2 horas
3. **¿Has experimentado retrasos o cruces de horario al llegar a la barbería con cita agendada?**
   - [ ] Frecuentemente
   - [ ] Rara vez
   - [ ] Nunca
4. **¿Te gustaría contar con un sitio web para ver la disponibilidad de tu barbero favorito y agendar tu cita 24/7?**
   - [ ] Totalmente de acuerdo
   - [ ] Indiferente
   - [ ] En desacuerdo
5. **¿Qué método de pago prefieres utilizar al finalizar tu servicio?**
   - [ ] Transferencia (Nequi / Daviplata)
   - [ ] Efectivo
   - [ ] Tarjeta de crédito / débito
6. **¿Te resultaría útil recibir confirmaciones y recordatorios automáticos previos a tu cita?**
   - [ ] Sí, muy útil
   - [ ] Indiferente
   - [ ] No
7. **¿Con qué frecuencia visitas la barbería para mantenimiento o corte?**
   - [ ] Semanal / Quincenal
   - [ ] Mensual
   - [ ] Ocasional

---

## 7. Tabulación de Resultados y Oportunidades de Mejora

### Resumen de Resultados (Muestra: 20 clientes encuestados)
* **80%** de los clientes reserva vía WhatsApp y manifiesta demoras considerables cuando el personal está ocupado atendiendo.
* **45%** de los usuarios ha sufrido retrasos de más de 15 minutos o cruce de agendas debido al registro manual.
* **95%** de los encuestados afirma que preferiría agendar sus turnos directamente desde una plataforma web interactiva.
* **90%** solicita métodos de pago ágiles y confirmaciones automáticas de reserva.

### Oportunidades de Mejora Identificadas

1. **Agendamiento y Reservas Web 24/7:** Desarrollo de un módulo de autogestión donde los clientes elijan servicio, barbero y horario sin interrupciones por WhatsApp.
2. **Gestión de Barberos y Control de Agenda en Vivo:** Interfaz para que los barberos visualicen su cronograma diario, gestionen su disponibilidad e indiquen el estado del servicio en tiempo real.
3. **Módulo Financiero y Cálculo Automatizado de Comisiones:** Eliminación de revisiones manuales en papel mediante la liquidación automática de comisiones basada en el porcentaje configurado por servicio cobrado.
4. **Módulo Analítico de Horarios de Mayor Demanda:** Panel con reportes visuales de horas pico e indicadores clave para optimizar turnos de personal y mejorar la rentabilidad del negocio.