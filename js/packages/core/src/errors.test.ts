import assert from 'node:assert/strict'
import { test } from 'node:test'
import { SeoApiError } from './errors.js'

test('fieldError prefers the specific validation message over the generic one', () => {
  const error = new SeoApiError('The given data was invalid.', 422, {
    message: 'The given data was invalid.',
    errors: { source: ['The redirect target is not on an allowed host.'] },
  })

  assert.equal(error.fieldError('source'), 'The redirect target is not on an allowed host.')
})

test('fieldError falls back to the generic message when the field has none', () => {
  const error = new SeoApiError('The given data was invalid.', 422, {
    errors: { target: ['Required.'] },
  })

  assert.equal(error.fieldError('source'), 'The given data was invalid.')
})

test('fieldErrors returns null for a body with no errors envelope', () => {
  const error = new SeoApiError('Forbidden.', 403, { message: 'Forbidden.' })

  assert.equal(error.fieldErrors(), null)
})
