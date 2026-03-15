'use client'

import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { Switch } from '@/components/ui/switch'
import { cn } from '@/lib/utils'

interface BaseFieldProps {
  label: string
  name: string
  error?: string
  required?: boolean
  description?: string
  className?: string
}

interface InputFieldProps extends BaseFieldProps {
  type?: 'text' | 'email' | 'password' | 'number' | 'tel' | 'url'
  value: string | number
  onChange: (value: string) => void
  placeholder?: string
  disabled?: boolean
  autoComplete?: string
}

export function InputField({
  label,
  name,
  type = 'text',
  value,
  onChange,
  placeholder,
  error,
  required,
  description,
  disabled,
  autoComplete,
  className,
}: InputFieldProps) {
  return (
    <div className={cn('space-y-2', className)}>
      <Label htmlFor={name} className="text-sm font-medium text-foreground">
        {label}
        {required && <span className="text-destructive ml-1">*</span>}
      </Label>
      <Input
        id={name}
        name={name}
        type={type}
        value={value}
        onChange={(e) => onChange(e.target.value)}
        placeholder={placeholder}
        disabled={disabled}
        autoComplete={autoComplete}
        className={cn(
          'bg-card border-border/50',
          error && 'border-destructive focus-visible:ring-destructive'
        )}
      />
      {description && !error && (
        <p className="text-xs text-muted-foreground">{description}</p>
      )}
      {error && <p className="text-xs text-destructive">{error}</p>}
    </div>
  )
}

interface TextareaFieldProps extends BaseFieldProps {
  value: string
  onChange: (value: string) => void
  placeholder?: string
  disabled?: boolean
  rows?: number
}

export function TextareaField({
  label,
  name,
  value,
  onChange,
  placeholder,
  error,
  required,
  description,
  disabled,
  rows = 4,
  className,
}: TextareaFieldProps) {
  return (
    <div className={cn('space-y-2', className)}>
      <Label htmlFor={name} className="text-sm font-medium text-foreground">
        {label}
        {required && <span className="text-destructive ml-1">*</span>}
      </Label>
      <Textarea
        id={name}
        name={name}
        value={value}
        onChange={(e) => onChange(e.target.value)}
        placeholder={placeholder}
        disabled={disabled}
        rows={rows}
        className={cn(
          'bg-card border-border/50 resize-none',
          error && 'border-destructive focus-visible:ring-destructive'
        )}
      />
      {description && !error && (
        <p className="text-xs text-muted-foreground">{description}</p>
      )}
      {error && <p className="text-xs text-destructive">{error}</p>}
    </div>
  )
}

interface SelectFieldProps extends BaseFieldProps {
  value: string
  onChange: (value: string) => void
  options: { value: string; label: string }[]
  placeholder?: string
  disabled?: boolean
}

export function SelectField({
  label,
  name,
  value,
  onChange,
  options,
  placeholder = 'Select...',
  error,
  required,
  description,
  disabled,
  className,
}: SelectFieldProps) {
  return (
    <div className={cn('space-y-2', className)}>
      <Label htmlFor={name} className="text-sm font-medium text-foreground">
        {label}
        {required && <span className="text-destructive ml-1">*</span>}
      </Label>
      <Select value={value} onValueChange={onChange} disabled={disabled}>
        <SelectTrigger
          id={name}
          className={cn(
            'bg-card border-border/50',
            error && 'border-destructive focus:ring-destructive'
          )}
        >
          <SelectValue placeholder={placeholder} />
        </SelectTrigger>
        <SelectContent>
          {options.map((option) => (
            <SelectItem key={option.value} value={option.value}>
              {option.label}
            </SelectItem>
          ))}
        </SelectContent>
      </Select>
      {description && !error && (
        <p className="text-xs text-muted-foreground">{description}</p>
      )}
      {error && <p className="text-xs text-destructive">{error}</p>}
    </div>
  )
}

interface SwitchFieldProps extends Omit<BaseFieldProps, 'name'> {
  name?: string
  checked: boolean
  onCheckedChange: (checked: boolean) => void
  disabled?: boolean
}

export function SwitchField({
  label,
  name,
  checked,
  onCheckedChange,
  error,
  description,
  disabled,
  className,
}: SwitchFieldProps) {
  return (
    <div className={cn('flex items-center justify-between', className)}>
      <div className="space-y-0.5">
        <Label htmlFor={name} className="text-sm font-medium text-foreground">
          {label}
        </Label>
        {description && (
          <p className="text-xs text-muted-foreground">{description}</p>
        )}
        {error && <p className="text-xs text-destructive">{error}</p>}
      </div>
      <Switch
        id={name}
        checked={checked}
        onCheckedChange={onCheckedChange}
        disabled={disabled}
      />
    </div>
  )
}

interface TagInputFieldProps extends BaseFieldProps {
  value: string[]
  onChange: (value: string[]) => void
  placeholder?: string
  disabled?: boolean
}

export function TagInputField({
  label,
  name,
  value,
  onChange,
  placeholder = 'Type and press Enter',
  error,
  required,
  description,
  disabled,
  className,
}: TagInputFieldProps) {
  const handleKeyDown = (e: React.KeyboardEvent<HTMLInputElement>) => {
    if (e.key === 'Enter') {
      e.preventDefault()
      const input = e.currentTarget
      const newTag = input.value.trim()
      if (newTag && !value.includes(newTag)) {
        onChange([...value, newTag])
        input.value = ''
      }
    }
  }

  const removeTag = (tagToRemove: string) => {
    onChange(value.filter((tag) => tag !== tagToRemove))
  }

  return (
    <div className={cn('space-y-2', className)}>
      <Label htmlFor={name} className="text-sm font-medium text-foreground">
        {label}
        {required && <span className="text-destructive ml-1">*</span>}
      </Label>
      <div
        className={cn(
          'flex flex-wrap gap-2 p-2 rounded-md bg-card border border-border/50 min-h-10',
          error && 'border-destructive'
        )}
      >
        {value.map((tag) => (
          <span
            key={tag}
            className="inline-flex items-center gap-1 px-2 py-1 text-xs bg-primary/20 text-primary rounded-md"
          >
            {tag}
            <button
              type="button"
              onClick={() => removeTag(tag)}
              className="hover:text-primary/80"
              disabled={disabled}
            >
              &times;
            </button>
          </span>
        ))}
        <input
          id={name}
          type="text"
          placeholder={value.length === 0 ? placeholder : ''}
          onKeyDown={handleKeyDown}
          disabled={disabled}
          className="flex-1 min-w-20 bg-transparent border-none outline-none text-sm placeholder:text-muted-foreground"
        />
      </div>
      {description && !error && (
        <p className="text-xs text-muted-foreground">{description}</p>
      )}
      {error && <p className="text-xs text-destructive">{error}</p>}
    </div>
  )
}
